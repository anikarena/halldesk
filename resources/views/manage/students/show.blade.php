<x-layouts.app :title="$student->name">
    @php
        $profile = $student->profile;
        $allocation = $student->activeAllocation;
        $isAdmin = auth()->user()->isAdmin();
    @endphp
    <x-page-header :title="$student->name" :back="route('manage.students.index')" back-label="Students"
                   :description="($profile?->student_id ?? 'No student ID').', '.($profile?->department ?? '').', '.($profile?->yearLabel() ?? '')">
        <x-slot:actions>
            @unless ($student->is_active)<x-badge tone="red">Deactivated</x-badge>@endunless
            @if ($isAdmin)
                <a href="{{ route('manage.students.edit', $student) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Edit</a>
                <a href="{{ route('manage.invoices.create', ['student' => $student->id]) }}" class="btn btn-secondary"><x-icon name="receipt" class="size-4" /> New bill</a>
                @unless ($allocation)
                    <a href="{{ route('manage.residents.create', ['student' => $student->id]) }}" class="btn btn-primary"><x-icon name="bed" class="size-4" /> Give a seat</a>
                @endunless
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Seat" :value="$allocation ? $allocation->seat->code() : 'None'" icon="bed"
                :hint="$allocation ? 'Since '.$allocation->start_date->format('j M Y') : 'Not living in the hall'" />
        <x-stat label="Amount due" :value="taka($due)" icon="receipt" :tone="$due ? 'red' : 'gray'" :hint="$due ? 'Unpaid bills' : 'No dues'" />
        <x-stat label="CGPA" :value="$profile?->cgpa ?? 'n/a'" icon="cap" tone="violet" :hint="$profile?->session ? 'Session '.$profile->session : null" />
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Bills</h2>
                    <a href="{{ route('manage.invoices.index', ['q' => $profile?->student_id]) }}" class="link text-sm">All bills</a>
                </div>
                @if ($invoices->isEmpty())
                    <x-empty icon="receipt" title="No bills yet" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Bill</th><th>Due</th><th class="text-right">Total</th><th class="text-right">Balance</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($invoices as $invoice)
                                    <tr>
                                        <td><a href="{{ route('manage.invoices.show', $invoice) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $invoice->title }}</a><span class="block text-xs text-slate-500">{{ $invoice->number }}</span></td>
                                        <td @class(['text-rose-600 font-medium' => $invoice->isOverdue()])>{{ $invoice->due_date->format('j M Y') }}</td>
                                        <td class="text-right tabular-nums">{{ taka($invoice->total) }}</td>
                                        <td class="text-right font-semibold tabular-nums">{{ taka($invoice->balance()) }}</td>
                                        <td><x-status :value="$invoice->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Seat history</h2></div>
                @if ($student->allocations->isEmpty())
                    <x-empty icon="bed" title="Never had a seat" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Seat</th><th>From</th><th>To</th><th>Status</th><th>Note</th></tr></thead>
                            <tbody>
                                @foreach ($student->allocations as $item)
                                    <tr>
                                        <td><a href="{{ route('manage.rooms.show', $item->seat->room) }}" class="font-mono text-xs font-semibold text-slate-800 hover:text-brand-700">{{ $item->seat->code() }}</a></td>
                                        <td>{{ $item->start_date->format('j M Y') }}</td>
                                        <td>{{ $item->end_date?->format('j M Y') ?? 'Now' }}</td>
                                        <td><x-status :value="$item->status" /></td>
                                        <td class="text-slate-500">{{ $item->end_reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Complaints</h2></div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($complaints as $complaint)
                            <li><a href="{{ route('manage.complaints.show', $complaint) }}" class="flex items-center justify-between gap-2 px-5 py-3 hover:bg-slate-50"><span class="truncate text-sm text-slate-800">{{ $complaint->title }}</span><x-status :value="$complaint->status" /></a></li>
                        @empty
                            <li><x-empty icon="wrench" title="No complaints" /></li>
                        @endforelse
                    </ul>
                </div>
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Recent visitors</h2></div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($visitors as $visitor)
                            <li class="px-5 py-3 text-sm"><span class="font-medium text-slate-800">{{ $visitor->visitor_name }}</span> <span class="text-slate-500">({{ $visitor->relation }}), {{ $visitor->check_in_at->format('j M, g:i A') }}</span></li>
                        @empty
                            <li><x-empty icon="door" title="No visitors" /></li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Contact</h2></div>
                <dl class="divide-y divide-slate-100 px-5">
                    <div class="dl-row"><dt>Phone</dt><dd>{{ $student->phone ?? 'n/a' }}</dd></div>
                    <div class="dl-row"><dt>Email</dt><dd class="truncate">{{ $student->email }}</dd></div>
                    <div class="dl-row"><dt>Blood group</dt><dd>{{ $profile?->blood_group ?? 'n/a' }}</dd></div>
                    <div class="dl-row"><dt>Home district</dt><dd>{{ $profile?->home_district ?? 'n/a' }}</dd></div>
                    <div class="dl-row"><dt>Address</dt><dd>{{ $profile?->permanent_address ?? 'n/a' }}</dd></div>
                </dl>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Guardian</h2></div>
                <dl class="divide-y divide-slate-100 px-5">
                    <div class="dl-row"><dt>Name</dt><dd>{{ $profile?->guardian_name ?? 'n/a' }}</dd></div>
                    <div class="dl-row"><dt>Relation</dt><dd>{{ $profile?->guardian_relation ?? 'n/a' }}</dd></div>
                    <div class="dl-row"><dt>Phone</dt><dd>{{ $profile?->guardian_phone ?? 'n/a' }}</dd></div>
                </dl>
            </div>

            @if ($isAdmin)
                <div class="card p-5">
                    <p class="text-sm font-semibold text-slate-800">Account</p>
                    <p class="mt-1 text-xs text-slate-500">Last sign in: {{ $student->last_login_at?->diffForHumans() ?? 'never' }}</p>
                    <div class="mt-4 flex flex-col gap-2">
                        <form method="POST" action="{{ route('manage.students.password', $student) }}" onsubmit="return confirm('Set a new random password for {{ $student->name }}?')">
                            @csrf
                            <button type="submit" class="btn btn-secondary w-full"><x-icon name="key" class="size-4" /> Reset password</button>
                        </form>
                        <form method="POST" action="{{ route('manage.students.toggle', $student) }}">
                            @csrf
                            <button type="submit" class="btn {{ $student->is_active ? 'btn-soft-danger' : 'btn-soft' }} w-full"><x-icon name="power" class="size-4" /> {{ $student->is_active ? 'Deactivate account' : 'Activate account' }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
