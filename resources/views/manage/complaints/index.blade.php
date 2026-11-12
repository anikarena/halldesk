<x-layouts.app title="Complaints">
    <x-page-header title="Complaints" description="High priority and oldest complaints are on top." />

    @php $base = \Illuminate\Support\Arr::except($filters, 'status'); @endphp
    <nav class="tabs mb-4">
        @foreach (['unresolved' => 'Not resolved', 'open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'all' => 'All'] as $key => $label)
            <a href="{{ route('manage.complaints.index', [...$base, 'status' => $key]) }}" @class(['tab', 'tab-active' => $status === $key])>
                {{ $label }}
                @if (isset($counts[$key]))<span class="tab-count">{{ $counts[$key] }}</span>@endif
            </a>
        @endforeach
    </nav>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="w-full sm:w-64"><x-input name="q" label="Search" :value="$filters['q'] ?? null" placeholder="Title, student, room number" /></div>
        <div class="w-36"><x-select name="priority" label="Priority" placeholder="Any" :value="$filters['priority'] ?? null" :options="\App\Enums\ComplaintPriority::options()" /></div>
        <div class="w-48"><x-select name="category" label="Category" placeholder="Any" :value="$filters['category'] ?? null" :options="\App\Enums\ComplaintCategory::options()" /></div>
        <div class="w-40"><x-select name="assigned" label="Assigned" placeholder="Anyone" :value="$filters['assigned'] ?? null" :options="['me' => 'To me', 'none' => 'Nobody yet']" /></div>
        <button type="submit" class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filter</button>
    </form>

    <div class="card">
        @if ($complaints->isEmpty())
            <x-empty icon="check-circle" title="No complaints here" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($complaints as $complaint)
                    <li>
                        <a href="{{ route('manage.complaints.show', $complaint) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 hover:bg-slate-50">
                            <span @class([
                                'h-10 w-1 shrink-0 rounded-full',
                                'bg-rose-500' => $complaint->priority->value === 'high',
                                'bg-sky-400' => $complaint->priority->value === 'medium',
                                'bg-slate-300' => $complaint->priority->value === 'low',
                            ])></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs text-slate-400">{{ $complaint->reference() }}</span>
                                    <span class="font-semibold text-slate-900">{{ $complaint->title }}</span>
                                    <x-status :value="$complaint->priority" />
                                </span>
                                <span class="mt-1 block text-sm text-slate-500">
                                    Room {{ $complaint->room?->number ?? 'n/a' }}, {{ $complaint->category->label() }}, {{ $complaint->user->name }}, {{ $complaint->created_at->diffForHumans() }}
                                </span>
                            </span>
                            <span class="flex items-center gap-3">
                                @if ($complaint->assignee)
                                    <span class="flex items-center gap-1.5 text-xs text-slate-500"><x-avatar :user="$complaint->assignee" size="sm" /> {{ $complaint->assignee->firstName() }}</span>
                                @else
                                    <span class="text-xs text-amber-700">Not assigned</span>
                                @endif
                                <x-status :value="$complaint->status" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-5 py-3">{{ $complaints->links() }}</div>
        @endif
    </div>
</x-layouts.app>
