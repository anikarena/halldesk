<x-layouts.app title="Seat application">
    @php
        $student = $application->user;
        $profile = $student->profile;
    @endphp
    <x-page-header :title="$student->name" :back="route('manage.applications.index')" back-label="Applications"
                   :description="'Applied '.$application->created_at->format('j M Y, g:i A').' ('.$application->created_at->diffForHumans().')'">
        <x-slot:actions><x-status :value="$application->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Application</h2></div>
                <div class="card-body space-y-5">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">CGPA</p><p class="text-xl font-semibold text-slate-900">{{ $profile?->cgpa ?? 'n/a' }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Year</p><p class="text-xl font-semibold text-slate-900">{{ $profile?->yearLabel() }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Home district</p><p class="text-xl font-semibold text-slate-900">{{ $profile?->home_district ?? 'n/a' }}</p></div>
                    </div>
                    <div>
                        <p class="mb-1.5 text-sm font-medium text-slate-700">Reason</p>
                        <div class="whitespace-pre-line rounded-lg border border-slate-200 p-4 text-sm leading-6 text-slate-700">{{ $application->reason }}</div>
                    </div>
                    <dl class="divide-y divide-slate-100">
                        <div class="dl-row"><dt>Room preference</dt><dd>{{ $application->preferenceLabel() }}</dd></div>
                        <div class="dl-row"><dt>Special needs</dt><dd>{{ $application->special_needs ?: 'None' }}</dd></div>
                        @if ($application->reviewer)
                            <div class="dl-row"><dt>Reviewed by</dt><dd>{{ $application->reviewer->name }}, {{ $application->reviewed_at?->format('j M Y') }}</dd></div>
                        @endif
                        @if ($application->decision_note)
                            <div class="dl-row"><dt>Decision note</dt><dd>{{ $application->decision_note }}</dd></div>
                        @endif
                        @if ($application->allocation)
                            <div class="dl-row"><dt>Seat given</dt><dd>{{ $application->allocation->seat->code() }} from {{ $application->allocation->start_date->format('j M Y') }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>

            @if ($application->isPending())
                <form method="POST" action="{{ route('manage.applications.approve', $application) }}" class="card border-brand-200">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Approve and give a seat</h2>
                            <p class="text-xs text-slate-500">Seats matching the preference ({{ $application->preferenceLabel() }}) are listed first.</p>
                        </div>
                    </div>
                    <div class="card-body space-y-5">
                        @if ($seats->isEmpty())
                            <x-alert tone="warning">No vacant seat right now. Vacate a seat or end maintenance on a room first.</x-alert>
                        @else
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-select name="seat_id" label="Seat" required placeholder="Choose a vacant seat"
                                          :options="$seats->mapWithKeys(fn ($s) => [$s->id => $s->code().'  ('.$s->room->typeLabel().', '.$s->room->floorLabel().', '.taka($s->room->monthly_rent).')'])->all()" />
                                <x-input name="start_date" type="date" label="Start date" required :value="today()->toDateString()" />
                            </div>
                            <x-input name="decision_note" label="Note for the student (optional)" placeholder="e.g. Collect your key from the office on Sunday" />
                        @endif
                    </div>
                    <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                        <button type="submit" class="btn btn-primary" @disabled($seats->isEmpty())><x-icon name="check" class="size-4" /> Approve and allocate</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('manage.applications.reject', $application) }}" class="card">
                    @csrf
                    <div class="card-header"><h2 class="card-title">Reject</h2></div>
                    <div class="card-body">
                        <x-textarea name="decision_note" label="Reason" rows="2" required placeholder="The student sees this reason." />
                    </div>
                    <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                        <button type="submit" class="btn btn-soft-danger" onclick="return confirm('Reject this application?')"><x-icon name="x" class="size-4" /> Reject application</button>
                    </div>
                </form>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card p-5">
                <div class="flex items-center gap-3">
                    <x-avatar :user="$student" size="lg" />
                    <div class="min-w-0">
                        <a href="{{ route('manage.students.show', $student) }}" class="block truncate font-semibold text-slate-900 hover:text-brand-700">{{ $student->name }}</a>
                        <p class="truncate text-sm text-slate-500">{{ $profile?->student_id }}</p>
                    </div>
                </div>
                <dl class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
                    <div class="dl-row"><dt>Department</dt><dd>{{ $profile?->department }}</dd></div>
                    <div class="dl-row"><dt>Session</dt><dd>{{ $profile?->session ?? 'n/a' }}</dd></div>
                    <div class="dl-row"><dt>Phone</dt><dd>{{ $student->phone ?? 'n/a' }}</dd></div>
                    <div class="dl-row"><dt>Email</dt><dd class="truncate">{{ $student->email }}</dd></div>
                    <div class="dl-row"><dt>Guardian</dt><dd>{{ $profile?->guardian_name ?? 'n/a' }}</dd></div>
                    <div class="dl-row"><dt>Guardian phone</dt><dd>{{ $profile?->guardian_phone ?? 'n/a' }}</dd></div>
                </dl>
            </div>

            @if ($others->isNotEmpty() || $student->allocations->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">History</h2></div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($student->allocations as $past)
                            <li class="flex items-center justify-between px-5 py-2.5"><span>Seat {{ $past->seat->code() }}, {{ $past->start_date->format('M Y') }}</span><x-status :value="$past->status" /></li>
                        @endforeach
                        @foreach ($others as $other)
                            <li class="flex items-center justify-between px-5 py-2.5"><span>Application {{ $other->created_at->format('j M Y') }}</span><x-status :value="$other->status" /></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
