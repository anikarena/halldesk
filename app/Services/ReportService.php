<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoomStatus;
use App\Models\Allocation;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the dashboards and the reports page.
 */
class ReportService
{
    /**
     * @return array{total: int, occupied: int, vacant: int, blocked: int, rate: int}
     */
    public function occupancy(): array
    {
        $total = Seat::query()->count();
        $occupied = Allocation::query()->active()->count();
        $blocked = Seat::query()
            ->whereHas('room', fn ($q) => $q->where('status', RoomStatus::Maintenance))
            ->whereDoesntHave('activeAllocation')
            ->count();

        return [
            'total' => $total,
            'occupied' => $occupied,
            'vacant' => max(0, $total - $occupied - $blocked),
            'blocked' => $blocked,
            'rate' => $total > 0 ? (int) round($occupied * 100 / $total) : 0,
        ];
    }

    /**
     * Seats and occupied seats per floor.
     *
     * @return Collection<int, array{floor: int, label: string, total: int, occupied: int}>
     */
    public function occupancyByFloor(): Collection
    {
        $occupied = Allocation::query()->active()
            ->join('seats', 'seats.id', '=', 'allocations.seat_id')
            ->join('rooms', 'rooms.id', '=', 'seats.room_id')
            ->groupBy('rooms.floor')
            ->selectRaw('rooms.floor as floor, count(*) as total')
            ->pluck('total', 'floor');

        return Seat::query()
            ->join('rooms', 'rooms.id', '=', 'seats.room_id')
            ->groupBy('rooms.floor')
            ->orderBy('rooms.floor')
            ->selectRaw('rooms.floor as floor, count(*) as total')
            ->get()
            ->map(fn ($row) => [
                'floor' => (int) $row->floor,
                'label' => Room::floorLabelFor((int) $row->floor),
                'total' => (int) $row->total,
                'occupied' => (int) ($occupied[$row->floor] ?? 0),
            ]);
    }

    /**
     * Seats and occupied seats per room size (single, double...).
     * Free seats in rooms under maintenance are not counted as vacant.
     *
     * @return Collection<int, array{capacity: int, label: string, rooms: int, total: int, occupied: int, vacant: int, rent: int, min_rent: int}>
     */
    public function occupancyByRoomType(): Collection
    {
        return Room::query()->withCount(['seats', 'activeAllocations'])->get()
            ->groupBy('capacity')
            ->sortKeys()
            ->map(fn (Collection $rooms, int $capacity) => [
                'capacity' => $capacity,
                'label' => Room::typeLabelFor($capacity),
                'rooms' => $rooms->count(),
                'total' => (int) $rooms->sum('seats_count'),
                'occupied' => (int) $rooms->sum('active_allocations_count'),
                'vacant' => (int) $rooms->reject(fn (Room $room) => $room->status === RoomStatus::Maintenance)
                    ->sum(fn (Room $room) => max(0, $room->seats_count - $room->active_allocations_count)),
                'rent' => (int) $rooms->avg('monthly_rent'),
                'min_rent' => (int) $rooms->min('monthly_rent'),
            ])
            ->values();
    }

    /**
     * Billed (monthly bills by billing month) and collected (verified payments by
     * the month they were verified) for the last $months months.
     *
     * @return Collection<int, array{period: string, label: string, billed: int, collected: int}>
     */
    public function financeByMonth(int $months = 6): Collection
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonthsNoOverflow($months - 1);
        $periods = collect(range(0, $months - 1))->map(fn ($i) => $start->addMonthsNoOverflow($i)->format('Y-m'));

        $billed = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->whereIn('period', $periods)
            ->groupBy('period')
            ->selectRaw('period, sum(total) as total')
            ->pluck('total', 'period');

        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', verified_at)"
            : "DATE_FORMAT(verified_at, '%Y-%m')";

        $collected = Payment::query()
            ->where('status', PaymentStatus::Verified)
            ->where('verified_at', '>=', $start)
            ->groupByRaw($monthExpression)
            ->selectRaw("{$monthExpression} as month, sum(amount) as total")
            ->pluck('total', 'month');

        return $periods->map(fn (string $period) => [
            'period' => $period,
            'label' => CarbonImmutable::createFromFormat('!Y-m', $period)->format('M Y'),
            'billed' => (int) ($billed[$period] ?? 0),
            'collected' => (int) ($collected[$period] ?? 0),
        ]);
    }

    /**
     * @return array{amount: int, students: int, overdue_amount: int, overdue_students: int}
     */
    public function outstanding(): array
    {
        $open = Invoice::query()->outstanding();
        $overdue = Invoice::query()->overdue();

        return [
            'amount' => (int) (clone $open)->sum(DB::raw('total - paid')),
            'students' => (int) (clone $open)->distinct()->count('user_id'),
            'overdue_amount' => (int) (clone $overdue)->sum(DB::raw('total - paid')),
            'overdue_students' => (int) (clone $overdue)->distinct()->count('user_id'),
        ];
    }

    public function collectedThisMonth(): int
    {
        return (int) Payment::query()
            ->where('status', PaymentStatus::Verified)
            ->where('verified_at', '>=', CarbonImmutable::today()->startOfMonth())
            ->sum('amount');
    }

    /**
     * Complaint counts per category, plus the average hours to resolve.
     *
     * @return array{by_category: Collection<int, array{label: string, open: int, total: int}>, avg_hours: ?int, open: int, resolved_30d: int}
     */
    public function complaintStats(): array
    {
        $rows = Complaint::query()
            ->groupBy('category')
            ->selectRaw("category, count(*) as total, sum(case when status != 'resolved' then 1 else 0 end) as open")
            ->get()
            ->map(fn ($row) => [
                'label' => $row->category->label(),
                'open' => (int) $row->open,
                'total' => (int) $row->total,
            ])
            ->sortByDesc('total')
            ->values();

        $resolved = Complaint::query()
            ->where('status', ComplaintStatus::Resolved)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(90))
            ->get(['created_at', 'resolved_at']);

        return [
            'by_category' => $rows,
            'avg_hours' => $resolved->isEmpty() ? null : (int) round($resolved->avg(fn ($c) => $c->created_at->diffInHours($c->resolved_at))),
            'open' => Complaint::query()->unresolved()->count(),
            'resolved_30d' => Complaint::query()->where('status', ComplaintStatus::Resolved)->where('resolved_at', '>=', now()->subDays(30))->count(),
        ];
    }
}
