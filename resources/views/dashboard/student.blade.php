<x-layouts.app title="Dashboard">
    <x-page-header :description="$user->profile ? $user->profile->student_id.', '.$user->profile->department.', '.$user->profile->yearLabel() : null">
        <x-slot:title>@include('partials.greeting')</x-slot:title>
    </x-page-header>

    {{-- The most important things first --}}
    @php
        $overdueTotal = $overdue->sum(fn ($i) => $i->balance());
        $nextDue = $openInvoices->first();
    @endphp
    @if ($overdueTotal || $due || ($meal && $meal['tomorrowChangeable']) || (! $allocation && $application))
        <section class="mb-8 grid gap-3 md:grid-cols-2">
            @if ($overdueTotal)
                <x-attention tone="red" icon="alert" :title="taka($overdueTotal).' is overdue'" :href="route('student.bills.index')" action="Pay now">
                    The due date has passed. Please pay as soon as possible to avoid a fine.
                </x-attention>
            @elseif ($due)
                <x-attention tone="amber" icon="receipt" :title="taka($due).' to pay'" :href="route('student.bills.show', $nextDue)" action="Pay">
                    Next due date: {{ $nextDue->due_date->format('l, j F') }}.
                </x-attention>
            @endif

            @if ($meal && $meal['tomorrowChangeable'])
                <x-attention tone="blue" icon="clock" title="Meal changes for tomorrow close soon" :href="route('student.meals')" action="Meals">
                    Change before {{ $meal['tomorrowCutoff']->format('g:i A') }} today ({{ $meal['tomorrowCutoff']->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]) }}).
                </x-attention>
            @endif

            @if (! $allocation && $application)
                <x-attention :tone="$application->isPending() ? 'amber' : ($application->status->value === 'rejected' ? 'red' : 'blue')" icon="clipboard"
                             :title="'Seat application: '.$application->status->label()" :href="route('student.apply')" action="Details">
                    Submitted {{ $application->created_at->format('j M Y') }}.
                    @if ($application->decision_note) Note from the office: {{ $application->decision_note }} @endif
                </x-attention>
            @endif
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Seat --}}
            @if ($allocation)
                <div class="card overflow-hidden">
                    <div class="flex bg-cream">
                        <img src="{{ asset(\App\Models\Room::photoFor($allocation->seat->room->capacity)) }}" alt="" class="hidden w-40 shrink-0 object-cover sm:block">
                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-5 px-6 py-7">
                            <div class="min-w-0 flex-1 basis-56">
                                <p class="eyebrow">Your seat</p>
                                <p class="display mt-2 text-4xl">Room {{ $allocation->seat->room->number }}, Seat {{ $allocation->seat->label }}</p>
                                <p class="mt-1.5 text-sm text-slate-600">{{ $allocation->seat->room->floorLabel() }}, {{ $allocation->seat->room->typeLabel() }} room. Since {{ $allocation->start_date->format('j M Y') }}</p>
                            </div>
                            <a href="{{ route('student.room') }}" class="btn btn-lg btn-arrow bg-white text-slate-800 shadow-sm hover:bg-sand"><span>Room details</span><x-icon name="arrow-right" /></a>
                        </div>
                    </div>
                    <div class="px-6 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Roommates</p>
                        @forelse ($roommates as $mate)
                            <div class="flex items-center gap-3 py-1.5">
                                <x-avatar :user="$mate->user" size="sm" />
                                <span class="min-w-0 flex-1 text-sm"><span class="font-medium text-slate-800">{{ $mate->user->name }}</span><span class="text-slate-500">, seat {{ $mate->seat->label }}, {{ $mate->user->profile?->department }}</span></span>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">You have the room to yourself right now.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="card p-6">
                    <div class="flex flex-wrap items-center gap-5">
                        <div class="grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-700"><x-icon name="bed" class="size-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-semibold text-slate-900">You do not have a seat yet</p>
                            <p class="text-sm text-slate-500">
                                @if ($application?->isPending())
                                    Your application is with the hall office. You will get a notification when it is decided.
                                @else
                                    Apply online. The office reviews applications by merit and need.
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('student.apply') }}" class="btn btn-primary">{{ $application?->isPending() ? 'View application' : 'Apply for a seat' }}</a>
                    </div>
                </div>
            @endif

            {{-- Meals --}}
            @if ($meal)
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Meals</h2>
                            <p class="text-xs text-slate-500">This month so far: {{ $meal['month']['lunch'] }} lunches, {{ $meal['month']['dinner'] }} dinners, about {{ taka($meal['month']['cost']) }}</p>
                        </div>
                        <a href="{{ route('student.meals') }}" class="link text-sm">Plan 14 days</a>
                    </div>
                    <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                        @foreach (['today' => $meal['today'], 'tomorrow' => $meal['tomorrow']] as $which => $day)
                            <div class="p-5">
                                <p class="text-sm font-semibold text-slate-800">{{ ucfirst($which) }} <span class="font-normal text-slate-500">{{ $day->format('D, j M') }}</span></p>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    @foreach (\App\Enums\Meal::cases() as $m)
                                        @include('partials.meal-toggle', [
                                            'date' => $day,
                                            'meal' => $m,
                                            'on' => ! isset($meal['offs'][$day->toDateString().'|'.$m->value]),
                                            'canChange' => $which === 'tomorrow' && $meal['tomorrowChangeable'],
                                        ])
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Notices --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Notice board</h2>
                    <a href="{{ route('notices.index') }}" class="link text-sm">All notices</a>
                </div>
                @include('partials.notice-list', ['notices' => $notices])
            </div>
        </div>

        <div class="space-y-6">
            {{-- Money --}}
            <div class="card p-5">
                <p class="text-sm font-medium text-slate-500">Amount due</p>
                <p @class(['mt-1 text-3xl font-semibold tracking-tight', 'text-rose-600' => $overdueTotal, 'text-slate-900' => ! $overdueTotal])>{{ taka($due) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    @if ($nextDue)
                        {{ $openInvoices->count() }} unpaid {{ str('bill')->plural($openInvoices->count()) }}. Next due {{ $nextDue->due_date->format('j M Y') }}.
                    @else
                        You have no unpaid bills. Thank you.
                    @endif
                </p>
                <a href="{{ route('student.bills.index') }}" class="btn btn-secondary mt-4 w-full">Bills and payments</a>
            </div>

            {{-- Complaints --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My open complaints</h2>
                    @if ($allocation)
                        <a href="{{ route('student.complaints.create') }}" class="btn btn-soft btn-sm"><x-icon name="plus" class="size-3.5" /> New</a>
                    @endif
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($complaints as $complaint)
                        <li>
                            <a href="{{ route('student.complaints.show', $complaint) }}" class="block px-5 py-3 hover:bg-slate-50">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-medium text-slate-800">{{ $complaint->title }}</span>
                                    <x-status :value="$complaint->status" />
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $complaint->reference() }}, {{ $complaint->created_at->diffForHumans() }}</span>
                            </a>
                        </li>
                    @empty
                        <li><x-empty icon="check-circle" title="Nothing open">Something broken in your room? Report it here.</x-empty></li>
                    @endforelse
                </ul>
            </div>

            {{-- Office --}}
            <div class="card p-5 text-sm">
                <p class="font-semibold text-slate-800">Hall office</p>
                <p class="mt-2 flex items-center gap-2 text-slate-600"><x-icon name="phone" class="size-4 text-slate-400" /> {{ setting('contact_phone') }}</p>
                <p class="mt-1.5 flex items-center gap-2 text-slate-600"><x-icon name="mail" class="size-4 text-slate-400" /> {{ setting('contact_email') }}</p>
                <p class="mt-1.5 flex items-center gap-2 text-slate-600"><x-icon name="map-pin" class="size-4 text-slate-400" /> {{ setting('hall_address') }}</p>
            </div>
        </div>
    </div>
</x-layouts.app>
