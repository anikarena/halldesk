<?php

namespace App\Http\Controllers\Manage;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoomRequest;
use App\Models\Allocation;
use App\Models\Room;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    /**
     * Floor map (default) or table of all rooms.
     */
    public function index(Request $request, ReportService $reports): View
    {
        $filters = $request->validate([
            'floor' => ['nullable', 'integer'],
            'capacity' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(RoomStatus::class)],
            'availability' => ['nullable', Rule::in(['vacant', 'full'])],
            'view' => ['nullable', Rule::in(['map', 'list'])],
        ]);

        $rooms = Room::query()
            ->with(['seats.activeAllocation.user.profile'])
            ->withCount(['seats', 'activeAllocations'])
            ->when(isset($filters['floor']), fn ($q) => $q->where('floor', $filters['floor']))
            ->when($filters['capacity'] ?? null, fn ($q, $c) => $q->where('capacity', $c))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('floor')
            ->orderByRaw('length(number)')
            ->orderBy('number')
            ->get();

        if (($filters['availability'] ?? null) === 'vacant') {
            $rooms = $rooms->filter(fn (Room $r) => $r->isAvailable() && $r->active_allocations_count < $r->seats_count);
        } elseif (($filters['availability'] ?? null) === 'full') {
            $rooms = $rooms->filter(fn (Room $r) => $r->active_allocations_count >= $r->seats_count);
        }

        return view('manage.rooms.index', [
            'rooms' => $rooms,
            'byFloor' => $rooms->groupBy('floor'),
            'occupancy' => $reports->occupancy(),
            'floors' => Room::query()->distinct()->orderBy('floor')->pluck('floor'),
            'capacities' => Room::query()->distinct()->orderBy('capacity')->pluck('capacity'),
            'filters' => $filters,
            'view' => $filters['view'] ?? 'map',
        ]);
    }

    public function show(Room $room): View
    {
        $room->load(['seats.activeAllocation.user.profile']);

        return view('manage.rooms.show', [
            'room' => $room,
            'history' => Allocation::query()
                ->whereIn('seat_id', $room->seats->pluck('id'))
                ->where('status', 'ended')
                ->with('user.profile', 'seat')
                ->latest('end_date')
                ->limit(15)
                ->get(),
            'complaints' => $room->complaints()->with('user')->latest()->limit(8)->get(),
        ]);
    }

    public function create(): View
    {
        return view('manage.rooms.form', [
            'room' => new Room(['status' => RoomStatus::Available, 'capacity' => 2, 'floor' => 1]),
        ]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $room = DB::transaction(function () use ($request) {
            $room = Room::query()->create($request->validated());
            foreach (Room::seatLabels($room->capacity) as $label) {
                $room->seats()->create(['label' => $label]);
            }

            return $room;
        });

        return redirect()->route('manage.rooms.show', $room)
            ->with('success', "Room {$room->number} was added with {$room->capacity} ".str('seat')->plural($room->capacity).'.');
    }

    public function edit(Room $room): View
    {
        return view('manage.rooms.form', ['room' => $room]);
    }

    /**
     * Raising the capacity adds seats. Lowering it removes the last seats, but
     * only if those seats were never used (their history must be kept).
     */
    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $data = $request->validated();
        $wanted = Room::seatLabels((int) $data['capacity']);
        $existing = $room->seats()->get()->keyBy('label');
        $extra = $existing->except($wanted);

        foreach ($extra as $seat) {
            if ($seat->allocations()->exists()) {
                return back()->withInput()->with('error', "Seat {$room->number}-{$seat->label} has residents or history, so the room cannot be made smaller. Set it under maintenance instead.");
            }
        }

        DB::transaction(function () use ($room, $data, $wanted, $existing, $extra) {
            foreach ($wanted as $label) {
                if (! $existing->has($label)) {
                    $room->seats()->create(['label' => $label]);
                }
            }
            $extra->each->delete();
            $room->update($data);
        });

        return redirect()->route('manage.rooms.show', $room)->with('success', "Room {$room->number} was updated.");
    }

    public function destroy(Room $room): RedirectResponse
    {
        $used = Allocation::query()->whereIn('seat_id', $room->seats()->pluck('id'))->exists();
        if ($used) {
            return back()->with('error', "Room {$room->number} has allocation history and cannot be deleted. Set it under maintenance instead.");
        }

        $room->delete();

        return redirect()->route('manage.rooms.index')->with('success', "Room {$room->number} was deleted.");
    }
}
