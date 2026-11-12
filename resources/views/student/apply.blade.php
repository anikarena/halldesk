<x-layouts.app title="Apply for a seat">
    <x-page-header title="Apply for a seat" description="The hall office reviews applications by academic merit, seniority and need." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($pending)
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Your application</h2>
                        <x-status :value="$pending->status" />
                    </div>
                    <div class="card-body">
                        {{-- Simple progress steps --}}
                        <ol class="grid grid-cols-3 gap-2 text-center text-xs font-medium">
                            <li class="rounded-lg bg-brand-50 px-2 py-2.5 text-brand-800"><x-icon name="check" class="mx-auto mb-1 size-4" />Submitted</li>
                            <li class="rounded-lg bg-amber-50 px-2 py-2.5 text-amber-800 ring-1 ring-amber-200"><x-icon name="clock" class="mx-auto mb-1 size-4" />Under review</li>
                            <li class="rounded-lg bg-slate-50 px-2 py-2.5 text-slate-400"><x-icon name="bed" class="mx-auto mb-1 size-4" />Seat given</li>
                        </ol>

                        <dl class="mt-6 divide-y divide-slate-100">
                            <div class="dl-row"><dt>Submitted</dt><dd>{{ $pending->created_at->format('j M Y, g:i A') }}</dd></div>
                            <div class="dl-row"><dt>Room preference</dt><dd>{{ $pending->preferenceLabel() }}</dd></div>
                            @if ($pending->special_needs)
                                <div class="dl-row"><dt>Special needs</dt><dd>{{ $pending->special_needs }}</dd></div>
                            @endif
                        </dl>
                        <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-700">{{ $pending->reason }}</div>

                        <form method="POST" action="{{ route('student.apply.cancel', $pending) }}" class="mt-6" onsubmit="return confirm('Cancel this application? You can apply again later.')">
                            @csrf
                            <button type="submit" class="btn btn-soft-danger">Cancel application</button>
                        </form>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('student.apply.store') }}" class="card">
                    @csrf
                    <div class="card-header"><h2 class="card-title">Application form</h2></div>
                    <div class="card-body space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-select name="preferred_capacity" label="Room preference" placeholder="Any room is fine"
                                      :options="$roomSizes->mapWithKeys(fn ($c) => [$c => \App\Models\Room::typeLabelFor($c).' room'])->all()"
                                      hint="We try to follow it, but it is not guaranteed." />
                            <x-input name="cgpa" type="number" step="0.01" min="0" max="4" label="Current CGPA" :value="$user->profile?->cgpa"
                                     hint="Leave empty if you are in your first semester." />
                        </div>

                        <x-textarea name="reason" label="Why do you need a seat?" required rows="5"
                                    hint="For example distance from home, family income, travel time. At least 30 characters." />

                        <x-input name="special_needs" label="Special needs (optional)" hint="Health condition, disability or anything the office should know." />

                        <div class="border-t border-slate-100 pt-5">
                            <p class="mb-4 text-sm font-semibold text-slate-800">Home and guardian</p>
                            <div class="grid gap-5 sm:grid-cols-3">
                                <x-input name="home_district" label="Home district" required :value="$user->profile?->home_district" placeholder="e.g. Rajshahi" />
                                <x-input name="guardian_name" label="Guardian name" required :value="$user->profile?->guardian_name" />
                                <x-input name="guardian_phone" label="Guardian phone" required :value="$user->profile?->guardian_phone" placeholder="01712 345678" />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-5 py-4">
                        <p class="mr-auto text-xs text-slate-500">You can have one application open at a time.</p>
                        <button type="submit" class="btn btn-primary">Submit application</button>
                    </div>
                </form>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card p-5">
                <p class="text-sm font-semibold text-slate-800">How it works</p>
                <ol class="mt-4 space-y-4 text-sm">
                    @foreach ([
                        ['Apply online', 'Fill this form once. You can cancel it while it is pending.'],
                        ['Review', 'The provost office checks CGPA, year and your reason.'],
                        ['Seat allocated', 'You get a notification with your room and seat number.'],
                        ['Move in', 'Collect your allotment letter and your key from the office.'],
                    ] as $i => [$step, $text])
                        <li class="flex gap-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">{{ $i + 1 }}</span>
                            <span><span class="font-medium text-slate-800">{{ $step }}.</span> <span class="text-slate-500">{{ $text }}</span></span>
                        </li>
                    @endforeach
                </ol>
            </div>

            @if ($history->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Past applications</h2></div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($history as $item)
                            <li class="px-5 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm text-slate-700">{{ $item->created_at->format('j M Y') }}</span>
                                    <x-status :value="$item->status" />
                                </div>
                                @if ($item->decision_note)
                                    <p class="mt-1 text-xs text-slate-500">Office note: {{ $item->decision_note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
