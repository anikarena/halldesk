<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Services\Telegram\StaffBot;
use App\Services\Telegram\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request, Telegram $telegram): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('profile'),
            'bloodGroups' => StudentProfile::BLOOD_GROUPS,
            'telegramReady' => $telegram->enabled(),
        ]);
    }

    /**
     * Hall office only: opens the bot in Telegram with a one time code that links this account.
     */
    public function connectTelegram(Request $request, Telegram $telegram, StaffBot $bot): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $username = $telegram->username();
        if (! $username) {
            return back()->with('error', 'The Telegram bot is not set up on this server.');
        }

        return redirect()->away("https://t.me/{$username}?start=".$bot->linkCode($request->user()));
    }

    public function disconnectTelegram(Request $request, Telegram $telegram): RedirectResponse
    {
        $user = $request->user();
        if ($user->telegram_chat_id) {
            $telegram->send($user->telegram_chat_id, 'Disconnected from HallDesk. You will not get the meal lists any more.');
            $user->forceFill(['telegram_chat_id' => null])->save();
        }

        return back()->with('success', 'Telegram is disconnected.');
    }

    /**
     * Students may update contact and guardian details. Student ID,
     * department and CGPA are official records, only the admin changes them.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s]{10,20}$/'],
        ], ['phone.regex' => 'Enter a valid phone number, for example 01712 345678.']);

        $user->update($data);

        if ($user->isStudent()) {
            $profile = $request->validate([
                'gender' => ['nullable', Rule::in(['male', 'female'])],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'blood_group' => ['nullable', Rule::in(StudentProfile::BLOOD_GROUPS)],
                'home_district' => ['nullable', 'string', 'max:60'],
                'permanent_address' => ['nullable', 'string', 'max:500'],
                'guardian_name' => ['nullable', 'string', 'max:100'],
                'guardian_relation' => ['nullable', 'string', 'max:40'],
                'guardian_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s]{10,20}$/'],
            ], ['guardian_phone.regex' => 'Enter a valid phone number, for example 01712 345678.']);

            $user->profile?->update($profile);
        }

        return back()->with('success', 'Your profile has been saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Your password has been changed.');
    }
}
