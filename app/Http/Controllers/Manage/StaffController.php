<?php

namespace App\Http\Controllers\Manage;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Accounts for the hall office: provost / admins and hall staff.
 */
class StaffController extends Controller
{
    public function index(): View
    {
        return view('manage.staff.index', [
            'members' => User::query()->managers()
                ->withCount(['assignedComplaints as assigned_open' => fn ($q) => $q->unresolved()])
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('manage.staff.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s]{10,20}$/'],
            'role' => ['required', Rule::in([Role::Admin->value, Role::Staff->value])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], ['phone.regex' => 'Enter a valid phone number, for example 01712 345678.']);

        $member = User::query()->create($data);

        return redirect()->route('manage.staff.index')->with('success', "{$member->name} was added as {$member->role->label()}.");
    }

    public function toggle(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->isManager(), 404);

        if ($member->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        if ($member->is_active && $member->isAdmin() && User::query()->where('role', Role::Admin)->where('is_active', true)->count() === 1) {
            return back()->with('error', 'At least one active admin is needed.');
        }

        $member->update(['is_active' => ! $member->is_active]);

        return back()->with('success', $member->is_active ? "{$member->name} can sign in again." : "{$member->name} can no longer sign in.");
    }
}
