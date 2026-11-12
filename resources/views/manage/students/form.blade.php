<x-layouts.app :title="$student->exists ? 'Edit '.$student->name : 'Add student'">
    <x-page-header :title="$student->exists ? 'Edit '.$student->name : 'Add a student'"
                   :back="$student->exists ? route('manage.students.show', $student) : route('manage.students.index')"
                   description="Fields with a red star are required." />

    <form method="POST" action="{{ $student->exists ? route('manage.students.update', $student) : route('manage.students.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($student->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Account</h2></div>
            <div class="card-body grid gap-5 sm:grid-cols-2">
                <x-input name="name" label="Full name" required :value="$student->name" />
                <x-input name="email" type="email" label="Email" required :value="$student->email" />
                <x-input name="phone" label="Mobile number" :value="$student->phone" placeholder="01712 345678" />
                <x-input name="password" type="password" :label="$student->exists ? 'New password' : 'Password'" :required="! $student->exists"
                         :hint="$student->exists ? 'Leave empty to keep the current password.' : 'At least 8 characters. Give it to the student.'" autocomplete="new-password" />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Academic</h2></div>
            <div class="card-body grid gap-5 sm:grid-cols-3">
                <x-input name="student_id" label="Student ID" required :value="$profile->student_id" />
                <div class="sm:col-span-2">
                    <x-select name="department" label="Department" required placeholder="Choose" :value="$profile->department" :options="array_combine($departments, $departments)" />
                </div>
                <x-select name="program" label="Program" required :value="$profile->program" :options="['BSc' => 'BSc', 'BBA' => 'BBA', 'BA' => 'BA', 'BPharm' => 'BPharm', 'LLB' => 'LLB', 'BArch' => 'BArch', 'MSc' => 'MSc', 'MBA' => 'MBA']" />
                <x-select name="year_of_study" label="Year" required :value="$profile->year_of_study" :options="[1 => '1st year', 2 => '2nd year', 3 => '3rd year', 4 => '4th year', 5 => '5th year / Masters']" />
                <x-input name="session" label="Session" :value="$profile->session" placeholder="2022-23" />
                <x-input name="cgpa" type="number" step="0.01" min="0" max="4" label="CGPA" :value="$profile->cgpa" />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Personal and guardian</h2></div>
            <div class="card-body grid gap-5 sm:grid-cols-3">
                <x-select name="gender" label="Gender" placeholder="Not given" :value="$profile->gender" :options="['male' => 'Male', 'female' => 'Female']" />
                <x-input name="date_of_birth" type="date" label="Date of birth" :value="$profile->date_of_birth?->toDateString()" />
                <x-select name="blood_group" label="Blood group" placeholder="Not given" :value="$profile->blood_group" :options="array_combine($bloodGroups, $bloodGroups)" />
                <x-input name="home_district" label="Home district" :value="$profile->home_district" />
                <div class="sm:col-span-2"><x-input name="permanent_address" label="Permanent address" :value="$profile->permanent_address" /></div>
                <x-input name="guardian_name" label="Guardian name" :value="$profile->guardian_name" />
                <x-input name="guardian_relation" label="Relation" :value="$profile->guardian_relation" />
                <x-input name="guardian_phone" label="Guardian phone" :value="$profile->guardian_phone" />
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ $student->exists ? route('manage.students.show', $student) : route('manage.students.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $student->exists ? 'Save changes' : 'Create student' }}</button>
        </div>
    </form>
</x-layouts.app>
