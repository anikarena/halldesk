<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gate register: who came to see which resident, and when they left.
 */
class VisitorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $history = Visitor::query()
            ->whereNotNull('check_out_at')
            ->with('host.profile', 'host.activeAllocation.seat.room')
            ->when($filters['date'] ?? null, fn ($q, $d) => $q->whereDate('check_in_at', $d))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('visitor_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('host', fn ($u) => $u->search($term))))
            ->latest('check_in_at')
            ->paginate(15)
            ->withQueryString();

        return view('manage.visitors.index', [
            'inside' => Visitor::query()->inside()->with('host.profile', 'host.activeAllocation.seat.room', 'recorder')->oldest('check_in_at')->get(),
            'history' => $history,
            'filters' => $filters,
            'residents' => User::query()->residents()->with('profile', 'activeAllocation.seat.room')->orderBy('name')->get(),
            'todayCount' => Visitor::query()->whereDate('check_in_at', today())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('allocations', 'user_id')->where('status', 'active')],
            'visitor_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s]{10,20}$/'],
            'relation' => ['required', 'string', 'max:40'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ], [
            'user_id.exists' => 'Choose a resident who currently has a seat.',
            'phone.regex' => 'Enter a valid phone number, for example 01712 345678.',
        ]);

        $visitor = Visitor::query()->create([
            ...$data,
            'check_in_at' => now(),
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', "{$visitor->visitor_name} checked in at ".now()->format('g:i A').'.');
    }

    public function checkout(Visitor $visitor): RedirectResponse
    {
        if (! $visitor->isInside()) {
            return back()->with('error', 'This visitor has already checked out.');
        }

        $visitor->update(['check_out_at' => now()]);

        return back()->with('success', "{$visitor->visitor_name} checked out.");
    }
}
