<?php

namespace App\Http\Controllers\Manage;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Seat;
use App\Models\SeatApplication;
use App\Notifications\HallAlert;
use App\Services\AllocationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'sort' => ['nullable', Rule::in(['oldest', 'merit', 'year'])],
        ]);
        $status = $filters['status'] ?? ApplicationStatus::Pending->value;
        $sort = $filters['sort'] ?? 'oldest';

        $query = SeatApplication::query()
            ->where('seat_applications.status', $status)
            ->with('user.profile', 'reviewer')
            ->select('seat_applications.*');

        // Merit list: highest CGPA first, senior students first on a tie
        if ($sort === 'merit' || $sort === 'year') {
            $query->join('student_profiles', 'student_profiles.user_id', '=', 'seat_applications.user_id');
            $sort === 'merit'
                ? $query->orderByRaw('student_profiles.cgpa is null')->orderByDesc('student_profiles.cgpa')->orderByDesc('student_profiles.year_of_study')
                : $query->orderByDesc('student_profiles.year_of_study')->orderByDesc('student_profiles.cgpa');
        }
        $query->orderBy('seat_applications.created_at', $status === 'pending' ? 'asc' : 'desc');

        $counts = SeatApplication::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('manage.applications.index', [
            'applications' => $query->paginate(15)->withQueryString(),
            'status' => $status,
            'sort' => $sort,
            'counts' => $counts,
            'vacantSeats' => Seat::query()->vacant()->count(),
        ]);
    }

    public function show(SeatApplication $application): View
    {
        $application->load('user.profile', 'user.allocations.seat.room', 'reviewer', 'allocation.seat.room');

        // Seats in the preferred room size first, then by room number
        $preferred = $application->preferred_capacity;
        $seats = Seat::query()->vacant()->with('room')->get()
            ->sort(fn (Seat $a, Seat $b) => (($b->room->capacity === $preferred) <=> ($a->room->capacity === $preferred))
                ?: strnatcmp($a->code(), $b->code()))
            ->values();

        return view('manage.applications.show', [
            'application' => $application,
            'seats' => $seats,
            'others' => $application->user->applications()->where('id', '!=', $application->id)->get(),
        ]);
    }

    public function approve(Request $request, SeatApplication $application, AllocationService $allocations): RedirectResponse
    {
        if (! $application->isPending()) {
            return back()->with('error', 'This application has already been '.strtolower($application->status->label()).'.');
        }

        $data = $request->validate([
            'seat_id' => ['required', 'integer', 'exists:seats,id'],
            'start_date' => ['required', 'date', 'after_or_equal:'.today()->subDays(30)->toDateString()],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        $allocation = $allocations->allocate(
            $application->user,
            Seat::query()->findOrFail($data['seat_id']),
            $request->user(),
            CarbonImmutable::parse($data['start_date']),
            $application,
        );

        if (! empty($data['decision_note'])) {
            $application->update(['decision_note' => $data['decision_note']]);
        }

        return redirect()->route('manage.applications.index')
            ->with('success', "{$application->user->name} now has seat {$allocation->seat->code()}.");
    }

    public function reject(Request $request, SeatApplication $application): RedirectResponse
    {
        if (! $application->isPending()) {
            return back()->with('error', 'This application has already been '.strtolower($application->status->label()).'.');
        }

        $data = $request->validate([
            'decision_note' => ['required', 'string', 'max:500'],
        ], ['decision_note.required' => 'Write a short reason, the student will see it.']);

        $application->update([
            'status' => ApplicationStatus::Rejected,
            'decision_note' => $data['decision_note'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $application->user->notify(new HallAlert(
            'Seat application not approved',
            'Reason: '.$data['decision_note'],
            route('student.apply'),
            'danger',
        ));

        return redirect()->route('manage.applications.index')->with('success', "The application of {$application->user->name} was rejected.");
    }
}
