<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $allocation = $user->activeAllocation()->with('seat.room.seats', 'allocatedBy')->first();

        if (! $allocation) {
            return redirect()->route('student.apply')->with('info', 'You do not have a seat yet. Apply below.');
        }

        $roomAllocations = Allocation::query()->active()
            ->whereIn('seat_id', $allocation->seat->room->seats->pluck('id'))
            ->with('user.profile')
            ->get()
            ->keyBy('seat_id');

        return view('student.room', [
            'allocation' => $allocation,
            'room' => $allocation->seat->room,
            'roomAllocations' => $roomAllocations,
            'history' => $user->allocations()->with('seat.room')->get(),
        ]);
    }
}
