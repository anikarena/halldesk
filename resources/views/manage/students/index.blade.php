<x-layouts.app title="Students">
    <x-page-header title="Students" description="Every student account: residents, applicants and former residents.">
        @if (auth()->user()->isAdmin())
            <x-slot:actions>
                <a href="{{ route('manage.students.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="size-4" /> Add student</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
        <div class="w-full sm:w-72"><x-input name="q" label="Search" :value="$filters['q'] ?? null" placeholder="Name, student ID, email, phone" /></div>
        <div class="w-44"><x-select name="type" label="Show" placeholder="All students" :value="$filters['type'] ?? null" :options="['resident' => 'Residents', 'applicant' => 'Without a seat', 'inactive' => 'Deactivated']" /></div>
        <div class="w-64"><x-select name="department" label="Department" placeholder="All departments" :value="$filters['department'] ?? null" :options="$departments->combine($departments)->all()" /></div>
        <button type="submit" class="btn btn-secondary"><x-icon name="search" class="size-4" /> Search</button>
        @if (array_filter($filters))<a href="{{ route('manage.students.index') }}" class="btn btn-ghost">Clear</a>@endif
    </form>

    <div class="card">
        @if ($students->isEmpty())
            <x-empty icon="id-card" title="No students found" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Student ID</th><th>Department</th><th>Year</th><th>Seat</th><th class="text-right">Due</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :user="$student" size="sm" />
                                        <div class="min-w-0">
                                            <a href="{{ route('manage.students.show', $student) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $student->name }}</a>
                                            <span class="block truncate text-xs text-slate-500">{{ $student->email }}</span>
                                        </div>
                                        @unless ($student->is_active)<x-badge tone="red">Deactivated</x-badge>@endunless
                                    </div>
                                </td>
                                <td class="font-mono text-xs">{{ $student->profile?->student_id }}</td>
                                <td class="max-w-56 truncate">{{ $student->profile?->department }}</td>
                                <td>{{ $student->profile?->yearLabel() }}</td>
                                <td>
                                    @if ($student->activeAllocation)
                                        <span class="inline-flex rounded-md bg-brand-50 px-2 py-0.5 font-mono text-xs font-semibold text-brand-800">{{ $student->activeAllocation->seat->code() }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">No seat</span>
                                    @endif
                                </td>
                                <td @class(['text-right tabular-nums', 'font-semibold text-rose-600' => $student->due_total > 0, 'text-slate-400' => ! $student->due_total])>{{ taka($student->due_total) }}</td>
                                <td class="text-right"><a href="{{ route('manage.students.show', $student) }}" class="btn btn-ghost btn-sm">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $students->links() }}</div>
        @endif
    </div>
</x-layouts.app>
