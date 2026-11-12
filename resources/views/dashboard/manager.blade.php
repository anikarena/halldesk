<x-layouts.app title="Dashboard">
    <x-page-header :description="'Here is what needs attention in '.setting('hall_name').' today.'">
        <x-slot:title>@include('partials.greeting')</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('manage.visitors.index') }}" class="btn btn-secondary"><x-icon name="door" class="size-4" /> Visitor check in</a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('manage.residents.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Allocate a seat</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Needs attention: only the items that are not zero --}}
    @php
        $hasAttention = $pendingApplicationCount && auth()->user()->isAdmin()
            || $pendingPaymentCount || $highComplaintCount || $outstanding['overdue_amount'] || $lastMonthUnbilled;
    @endphp
    <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
            <x-icon name="alert-circle" class="size-4" /> Needs your attention
        </h2>
        @if ($hasAttention)
            <div class="grid gap-3 md:grid-cols-2">
                @if ($lastMonthUnbilled)
                    <x-attention tone="brand" icon="sparkle" :title="'Bills for '.month_label($lastPeriod).' are not generated yet'" :href="route('manage.invoices.generate', ['period' => $lastPeriod])" action="Generate">
                        Residents cannot pay until their monthly bill is made.
                    </x-attention>
                @endif
                @if ($pendingApplicationCount && auth()->user()->isAdmin())
                    <x-attention tone="amber" icon="clipboard" :title="$pendingApplicationCount.' seat '.str('application')->plural($pendingApplicationCount).' waiting for review'" :href="route('manage.applications.index')" action="Review">
                        {{ $occupancy['vacant'] }} vacant {{ str('seat')->plural($occupancy['vacant']) }} available right now.
                    </x-attention>
                @endif
                @if ($pendingPaymentCount)
                    <x-attention tone="blue" icon="wallet" :title="$pendingPaymentCount.' '.str('payment')->plural($pendingPaymentCount).' to verify'" :href="route('manage.payments.index')" action="Check">
                        {{ taka($pendingPaymentSum) }} reported by students through bKash, Nagad or bank.
                    </x-attention>
                @endif
                @if ($highComplaintCount)
                    <x-attention tone="red" icon="wrench" :title="$highComplaintCount.' high priority '.str('complaint')->plural($highComplaintCount).' open'" :href="route('manage.complaints.index', ['priority' => 'high'])" action="Open">
                        {{ $openComplaintCount }} complaints in total are not picked up yet.
                    </x-attention>
                @endif
                @if ($outstanding['overdue_amount'])
                    <x-attention tone="red" icon="receipt" :title="taka($outstanding['overdue_amount']).' overdue'" :href="route('manage.invoices.index', ['status' => 'overdue'])" action="View">
                        {{ $outstanding['overdue_students'] }} {{ str('student')->plural($outstanding['overdue_students']) }} did not pay by the due date.
                    </x-attention>
                @endif
            </div>
        @else
            <x-alert tone="success" title="All clear">No pending applications, payments or urgent complaints right now.</x-alert>
        @endif
    </section>

    {{-- Key numbers --}}
    <section class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Seat occupancy" :value="$occupancy['rate'].'%'" icon="bed" :href="route('manage.rooms.index')"
                :hint="$occupancy['occupied'].' of '.$occupancy['total'].' seats taken, '.$occupancy['vacant'].' vacant'" />
        <x-stat label="Collected this month" :value="taka($collectedThisMonth)" icon="banknote" tone="blue" :href="route('manage.invoices.index')"
                :hint="taka($outstanding['amount']).' still due from '.$outstanding['students'].' students'" />
        <x-stat label="Meals today" :value="$mealsToday['lunch'].' / '.$mealsToday['dinner']" icon="utensils" tone="amber" :href="route('manage.meals.index')"
                :hint="'Lunch / dinner. Tomorrow: '.$mealsTomorrow['lunch'].' / '.$mealsTomorrow['dinner']" />
        <x-stat label="Visitors inside" :value="$visitorsInside" icon="door" tone="violet" :href="route('manage.visitors.index')"
                :hint="$visitorsInside ? 'Remember to check them out' : 'Nobody is inside right now'" />
    </section>

    <section class="mb-8 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Billed and collected</h2>
                    <p class="text-xs text-slate-500">Last 6 months</p>
                </div>
                <a href="{{ auth()->user()->isAdmin() ? route('manage.reports.index') : route('manage.invoices.index') }}" class="link text-sm">Details</a>
            </div>
            <div class="card-body">
                @include('partials.finance-chart', ['finance' => $finance])
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Occupancy by floor</h2>
                <a href="{{ route('manage.rooms.index') }}" class="link text-sm">Room map</a>
            </div>
            <div class="card-body space-y-5">
                @forelse ($floors as $floor)
                    <x-meter :value="$floor['occupied']" :max="$floor['total']" :label="$floor['label']"
                             :detail="$floor['occupied'].' of '.$floor['total'].' seats'" />
                @empty
                    <x-empty icon="grid" title="No rooms yet">Add rooms to see the occupancy.</x-empty>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        @if (auth()->user()->isAdmin())
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Seat applications</h2>
                    <a href="{{ route('manage.applications.index') }}" class="link text-sm">All</a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($pendingApplications as $application)
                        <li>
                            <a href="{{ route('manage.applications.show', $application) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                                <x-avatar :user="$application->user" size="sm" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-slate-800">{{ $application->user->name }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $application->user->profile?->department }}, {{ $application->user->profile?->yearLabel() }}</span>
                                </span>
                                <span class="text-xs text-slate-400">{{ $application->created_at->diffForHumans(short: true) }}</span>
                            </a>
                        </li>
                    @empty
                        <li><x-empty icon="clipboard" title="No pending applications" /></li>
                    @endforelse
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Payments to verify</h2>
                <a href="{{ route('manage.payments.index') }}" class="link text-sm">All</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($pendingPayments as $payment)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <x-avatar :user="$payment->user" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-800">{{ $payment->user->name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $payment->method->label() }}, TrxID {{ $payment->transaction_id }}</span>
                        </span>
                        <span class="text-sm font-semibold tabular-nums text-slate-900">{{ taka($payment->amount) }}</span>
                    </li>
                @empty
                    <li><x-empty icon="wallet" title="Nothing to verify" /></li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Open complaints</h2>
                <a href="{{ route('manage.complaints.index') }}" class="link text-sm">All</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($urgentComplaints as $complaint)
                    <li>
                        <a href="{{ route('manage.complaints.show', $complaint) }}" class="flex items-start gap-3 px-5 py-3 hover:bg-slate-50">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-800">{{ $complaint->title }}</span>
                                <span class="block truncate text-xs text-slate-500">Room {{ $complaint->room?->number ?? 'n/a' }}, {{ $complaint->created_at->diffForHumans() }}</span>
                            </span>
                            <x-status :value="$complaint->priority" />
                        </a>
                    </li>
                @empty
                    <li><x-empty icon="check-circle" title="No open complaints" /></li>
                @endforelse
            </ul>
        </div>

        @if (! auth()->user()->isAdmin())
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Notice board</h2>
                    <a href="{{ route('notices.index') }}" class="link text-sm">All</a>
                </div>
                @include('partials.notice-list', ['notices' => $notices])
            </div>
        @endif
    </section>
</x-layouts.app>
