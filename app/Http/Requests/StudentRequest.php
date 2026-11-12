<?php

namespace App\Http\Requests;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin creates or edits a student account with the full profile.
 */
class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $student */
        $student = $this->route('student');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($student?->id)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s]{10,20}$/'],
            'password' => [$student ? 'nullable' : 'required', Password::defaults()],
            ...self::profileRules($student?->profile?->id),
        ];
    }

    /**
     * Rules for the student_profiles fields, shared with the profile page.
     *
     * @return array<string, mixed>
     */
    public static function profileRules(?int $profileId = null): array
    {
        return [
            'student_id' => ['required', 'string', 'max:30', Rule::unique('student_profiles', 'student_id')->ignore($profileId)],
            'department' => ['required', 'string', 'max:80'],
            'program' => ['required', 'string', 'max:40'],
            'session' => ['nullable', 'string', 'max:20'],
            'year_of_study' => ['required', 'integer', 'between:1,5'],
            'cgpa' => ['nullable', 'numeric', 'between:0,4'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'blood_group' => ['nullable', Rule::in(StudentProfile::BLOOD_GROUPS)],
            'home_district' => ['nullable', 'string', 'max:60'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'guardian_relation' => ['nullable', 'string', 'max:40'],
            'guardian_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s]{10,20}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, for example 01712 345678.',
            'guardian_phone.regex' => 'Enter a valid phone number, for example 01712 345678.',
        ];
    }
}
