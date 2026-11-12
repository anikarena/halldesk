<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Students create their own account, then apply for a seat.
 * Staff and admin accounts are created by the admin only.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['departments' => StudentProfile::DEPARTMENTS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\s]{10,20}$/'],
            'student_id' => ['required', 'string', 'max:30', Rule::unique('student_profiles', 'student_id')],
            'department' => ['required', 'string', 'max:80'],
            'year_of_study' => ['required', 'integer', 'between:1,5'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'phone.regex' => 'Enter a valid phone number, for example 01712 345678.',
            'student_id.unique' => 'An account with this student ID already exists.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role' => Role::Student,
                'password' => $data['password'],
            ]);
            $user->profile()->create([
                'student_id' => strtoupper($data['student_id']),
                'department' => $data['department'],
                'year_of_study' => $data['year_of_study'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.apply')
            ->with('success', 'Welcome, '.$user->name.'. Your account is ready. You can now apply for a seat.');
    }
}
