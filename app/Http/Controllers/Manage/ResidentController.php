<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AllocationStatus;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use App\Services\AllocationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResidentController extends Controller
{
    public function __construct(private AllocationService $allocations) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(AllocationStatus::class)],
        ]);
        $status = $filters['status'] ?? AllocationStatus::Active->value;

        $allocations = Allocation::query()
            ->where('allocations.status', $status)
            ->with('user.profile', 'seat.room')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->search($term)))
            ->when(isset($filters['floor']), fn ($q) => $q->whereHas('seat.room', fn ($r) => $r->where('floor', $filters['floor'])))
            ->join('seats', 'seats.id', '=', 'allocations.seat_id')
            ->join('rooms', 'rooms.id', '=', 'seats.room_id')
            ->select('allocations.*')
            ->when($status === 'active',
                fn ($q) => $q->orderBy('rooms.floor')->orderByRaw('length(rooms.number)')->orderBy('rooms.number')->orderBy('seats.label'),
                fn ($q) => $q->orderByDesc('allocations.end_date'))
            ->paginate(25)
            ->withQueryString();

        return view('manage.residents.index', [
            'allocations' => $allocations,
            'filters' => $filters,
            'status' => $status,
            'floors' => Room::query()->distinct()->orderBy('floor')->pluck('floor'),
            'activeCount' => Allocation::query()->active()->count(),
        ]);
    }

    /**
     * Allocate a seat directly, without an application (for example a
     * student who applied on paper).
     */
    public function create(Request $request): View
    {
        return view('manage.residents.create', [
            'students' => User::query()->students()->where('is_active', true)
                ->whereDoesntHave('activeAllocation')->with('profile')->orderBy('name')->get(),
            'seats' => $this->vacantSeats(),
            'selectedStudent' => $request->integer('student') ?: null,
            'selectedSeat' => $request->integer('seat') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'seat_id' => ['required', 'integer', 'exists:seats,id'],
            'start_date' => ['required', 'date', 'after_or_equal:'.today()->subDays(30)->toDateString()],
        ], ['start_date.after_or_equal' => 'The start date can be at most 30 days in the past.']);

        $allocation = $this->allocations->allocate(
            User::query()->findOrFail($data['user_id']),
            Seat::query()->findOrFail($data['seat_id']),
            $request->user(),
            CarbonImmutable::parse($data['start_date']),
        );

        return redirect()->route('manage.residents.index')
            ->with('success', "{$allocation->user->name} was given seat {$allocation->seat->code()}.");
    }

    public function transferForm(Allocation $allocation): View
    {
        abort_unless($allocation->isActive(), 404);

        return view('manage.residents.transfer', [
            'allocation' => $allocation->load('user.profile', 'seat.room'),
            'seats' => $this->vacantSeats(),
        ]);
    }

    public function transfer(Request $request, Allocation $allocation): RedirectResponse
    {
        $data = $request->validate([
            'seat_id' => ['required', 'integer', 'exists:seats,id'],
            'date' => ['required', 'date'],
        ]);

        $new = $this->allocations->transfer(
            $allocation,
            Seat::query()->findOrFail($data['seat_id']),
            $request->user(),
            CarbonImmutable::parse($data['date']),
        );

        return redirect()->route('manage.residents.index')
            ->with('success', "{$new->user->name} moved to seat {$new->seat->code()}.");
    }

    public function vacate(Request $request, Allocation $allocation): RedirectResponse
    {
        $data = $request->validate([
            'last_day' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:255'],
        ], ['last_day.before_or_equal' => 'The last day cannot be in the future.']);

        $this->allocations->vacate($allocation, $request->user(), CarbonImmutable::parse($data['last_day']), $data['reason']);

        return redirect()->route('manage.residents.index')
            ->with('success', "{$allocation->user->name} has left seat {$allocation->seat->code()}.");
    }

    /**
     * @return Collection<int, Seat>
     */
    private function vacantSeats(): Collection
    {
        return Seat::query()->vacant()->with('room')->get()
            ->sort(fn (Seat $a, Seat $b) => ($a->room->floor <=> $b->room->floor) ?: strnatcmp($a->code(), $b->code()))
            ->values();
    }
}
