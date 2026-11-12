<?php

namespace App\Http\Controllers\Student;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\SeatApplication;
use App\Models\User;
use App\Notifications\HallAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user()->load('profile');

        if ($user->isResident()) {
            return redirect()->route('student.room')->with('info', 'You already have a seat in the hall.');
        }

        $applications = $user->applications()->with('reviewer')->get();

        return view('student.apply', [
            'user' => $user,
            'pending' => $applications->first(fn (SeatApplication $a) => $a->isPending()),
            'history' => $applications,
            'roomSizes' => Room::query()->distinct()->orderBy('capacity')->pluck('capacity'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isResident()) {
            return redirect()->route('student.room');
        }
        if ($user->applications()->where('status', ApplicationStatus::Pending)->exists()) {
            return back()->with('error', 'You already have an application waiting for review.');
        }

        $data = $request->validate([
            'preferred_capacity' => ['nullable', 'integer', Rule::in(Room::query()->distinct()->pluck('capacity'))],
            'reason' => ['required', 'string', 'min:30', 'max:1500'],
            'special_needs' => ['nullable', 'string', 'max:255'],
            'cgpa' => ['nullable', 'numeric', 'between:0,4'],
            'home_district' => ['required', 'string', 'max:60'],
            'guardian_name' => ['required', 'string', 'max:100'],
            'guardian_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\s]{10,20}$/'],
        ], [
            'reason.min' => 'Please write at least a few lines (30 characters) about why you need a seat.',
            'guardian_phone.regex' => 'Enter a valid phone number, for example 01712 345678.',
        ]);

        $application = DB::transaction(function () use ($user, $data) {
            $user->profile()->update([
                'cgpa' => $data['cgpa'] ?? null,
                'home_district' => $data['home_district'],
                'guardian_name' => $data['guardian_name'],
                'guardian_phone' => $data['guardian_phone'],
            ]);

            return $user->applications()->create([
                'preferred_capacity' => $data['preferred_capacity'] ?? null,
                'reason' => $data['reason'],
                'special_needs' => $data['special_needs'] ?? null,
                'status' => ApplicationStatus::Pending,
            ]);
        });

        Notification::send(
            User::query()->where('role', 'admin')->where('is_active', true)->get(),
            new HallAlert('New seat application', "{$user->name} applied for a seat.", route('manage.applications.show', $application), 'warning'),
        );

        return redirect()->route('student.apply')->with('success', 'Your application has been submitted. The hall office will review it soon.');
    }

    public function cancel(Request $request, SeatApplication $application): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, 403);

        if (! $application->isPending()) {
            return back()->with('error', 'Only a pending application can be cancelled.');
        }

        $application->update(['status' => ApplicationStatus::Cancelled]);

        return back()->with('success', 'Your application has been cancelled.');
    }
}
