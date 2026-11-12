<x-layouts.app title="Seat applications">
    <x-page-header title="Seat applications" :description="$vacantSeats.' vacant '.str('seat')->plural($vacantSeats).' available for allocation'" />

    <nav class="tabs mb-4">
        @foreach (\App\Enums\ApplicationStatus::cases() as $s)
            <a href="{{ route('manage.applications.index', ['status' => $s->value, 'sort' => $sort]) }}" @class(['tab', 'tab-active' => $status === $s->value])>
                {{ $s === \App\Enums\ApplicationStatus::Pending ? 'Waiting' : $s->label() }}
                <span class="tab-count">{{ $counts[$s->value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-slate-500">Sort by</span>
        @foreach (['oldest' => 'Date applied', 'merit' => 'Merit (CGPA)', 'year' => 'Seniority'] as $key => $label)
            <a href="{{ route('manage.applications.index', ['status' => $status, 'sort' => $key]) }}"
               @class(['rounded-full px-3 py-1 font-medium', 'bg-brand-700 text-white' => $sort === $key, 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' => $sort !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="card">
        @if ($applications->isEmpty())
            <x-empty icon="clipboard" title="No applications here" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        @if ($sort === 'merit')<th class="w-10">#</th>@endif
                        <th>Student</th><th>Department</th><th>Year</th><th>CGPA</th><th>Home district</th><th>Preference</th><th>Applied</th><th></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($applications as $application)
                            @php $profile = $application->user->profile; @endphp
                            <tr>
                                @if ($sort === 'merit')<td class="font-semibold text-slate-400">{{ $applications->firstItem() + $loop->index }}</td>@endif
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :user="$application->user" size="sm" />
                                        <div>
                                            <a href="{{ route('manage.applications.show', $application) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $application->user->name }}</a>
                                            <span class="block text-xs text-slate-500">{{ $profile?->student_id }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="max-w-48 truncate">{{ $profile?->department }}</td>
                                <td>{{ $profile?->yearLabel() }}</td>
                                <td class="font-semibold tabular-nums text-slate-900">{{ $profile?->cgpa ?? 'n/a' }}</td>
                                <td>{{ $profile?->home_district ?? 'n/a' }}</td>
                                <td>{{ $application->preferenceLabel() }}</td>
                                <td class="whitespace-nowrap text-slate-500">{{ $application->created_at->format('j M Y') }}</td>
                                <td class="text-right">
                                    <a href="{{ route('manage.applications.show', $application) }}" class="btn {{ $application->isPending() ? 'btn-primary' : 'btn-ghost' }} btn-sm">{{ $application->isPending() ? 'Review' : 'View' }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $applications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
