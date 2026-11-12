<?php

namespace App\Http\Controllers\Manage;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Models\Invoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['resident', 'applicant', 'inactive'])],
            'department' => ['nullable', 'string', 'max:80'],
        ]);

        $students = User::query()->students()
            ->with('profile', 'activeAllocation.seat.room')
            ->withSum(['invoices as due_total' => fn ($q) => $q->outstanding()], DB::raw('total - paid'))
            ->search($filters['q'] ?? null)
            ->when(($filters['type'] ?? null) === 'resident', fn ($q) => $q->whereHas('activeAllocation'))
            ->when(($filters['type'] ?? null) === 'applicant', fn ($q) => $q->whereDoesntHave('activeAllocation')->where('is_active', true))
            ->when(($filters['type'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['department'] ?? null, fn ($q, $d) => $q->whereHas('profile', fn ($p) => $p->where('department', $d)))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('manage.students.index', [
            'students' => $students,
            'filters' => $filters,
            'departments' => StudentProfile::query()->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    public function show(User $student): View
    {
        $this->ensureStudent($student);

        $student->load([
            'profile',
            'activeAllocation.seat.room',
            'allocations.seat.room',
            'applications',
        ]);

        $invoices = Invoice::query()->where('user_id', $student->id)->latest('due_date')->limit(8)->get();

        return view('manage.students.show', [
            'student' => $student,
            'invoices' => $invoices,
            'due' => (int) Invoice::query()->where('user_id', $student->id)->outstanding()->sum(DB::raw('total - paid')),
            'complaints' => $student->complaints()->latest()->limit(5)->get(),
            'visitors' => $student->visitors()->latest('check_in_at')->limit(5)->get(),
        ]);
    }

    public function create(): View
    {
        return view('manage.students.form', [
            'student' => new User(),
            'profile' => new StudentProfile(['program' => 'BSc', 'year_of_study' => 1]),
            'departments' => StudentProfile::DEPARTMENTS,
            'bloodGroups' => StudentProfile::BLOOD_GROUPS,
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $student = DB::transaction(function () use ($data) {
            $student = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'role' => Role::Student,
                'password' => $data['password'],
            ]);
            $student->profile()->create($this->profileData($data));

            return $student;
        });

        return redirect()->route('manage.students.show', $student)->with('success', "Student account for {$student->name} was created.");
    }

    public function edit(User $student): View
    {
        $this->ensureStudent($student);

        return view('manage.students.form', [
            'student' => $student,
            'profile' => $student->profile ?? new StudentProfile(),
            'departments' => StudentProfile::DEPARTMENTS,
            'bloodGroups' => StudentProfile::BLOOD_GROUPS,
        ]);
    }

    public function update(StudentRequest $request, User $student): RedirectResponse
    {
        $this->ensureStudent($student);
        $data = $request->validated();

        DB::transaction(function () use ($student, $data) {
            $student->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                ...(filled($data['password'] ?? null) ? ['password' => $data['password']] : []),
            ]);
            $student->profile()->updateOrCreate(['user_id' => $student->id], $this->profileData($data));
        });

        return redirect()->route('manage.students.show', $student)->with('success', 'Student details were saved.');
    }

    public function toggle(User $student): RedirectResponse
    {
        $this->ensureStudent($student);

        if ($student->is_active && $student->isResident()) {
            return back()->with('error', 'This student still has a seat. Vacate the seat before deactivating the account.');
        }

        $student->update(['is_active' => ! $student->is_active]);

        return back()->with('success', $student->is_active ? 'The account was activated.' : 'The account was deactivated. The student can no longer sign in.');
    }

    /**
     * Set a new random password and show it once, so the office can hand it over.
     */
    public function resetPassword(User $student): RedirectResponse
    {
        $this->ensureStudent($student);

        $password = Str::password(10, symbols: false);
        $student->update(['password' => $password]);

        return back()->with('success', "New password for {$student->name}: {$password}  (shown only once, give it to the student)");
    }

    private function ensureStudent(User $user): void
    {
        abort_unless($user->isStudent(), 404);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileData(array $data): array
    {
        $profile = array_intersect_key($data, StudentRequest::profileRules());
        $profile['student_id'] = strtoupper($profile['student_id']);

        return $profile;
    }
}
