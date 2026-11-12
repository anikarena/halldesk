<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('manage.settings', ['values' => $settings->all()]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'hall_name' => ['required', 'string', 'max:100'],
            'university_name' => ['required', 'string', 'max:120'],
            'hall_address' => ['required', 'string', 'max:200'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'contact_email' => ['required', 'email', 'max:120'],
            'lunch_rate' => ['required', 'integer', 'between:0,1000'],
            'dinner_rate' => ['required', 'integer', 'between:0,1000'],
            'meal_cutoff_time' => ['required', 'date_format:H:i'],
            'electricity_charge' => ['required', 'integer', 'between:0,10000'],
            'bill_due_day' => ['required', 'integer', 'between:1,28'],
            'bkash_number' => ['nullable', 'string', 'max:30'],
            'nagad_number' => ['nullable', 'string', 'max:30'],
            'rocket_number' => ['nullable', 'string', 'max:30'],
            'bank_account' => ['nullable', 'string', 'max:150'],
        ]);

        $settings->put($data);

        return back()->with('success', 'Settings were saved. New meal rates and the electricity charge apply to bills generated from now on.');
    }
}
