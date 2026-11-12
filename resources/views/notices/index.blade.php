<x-layouts.app title="Notice board">
    <x-page-header title="Notice board" description="Pinned and urgent notices are always on top.">
        @if (auth()->user()->isAdmin())
            <x-slot:actions>
                <a href="{{ route('manage.notices.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> New notice</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <nav class="tabs mb-6">
        <a href="{{ route('notices.index') }}" @class(['tab', 'tab-active' => ! $category])>All</a>
        @foreach (\App\Enums\NoticeCategory::cases() as $c)
            <a href="{{ route('notices.index', ['category' => $c->value]) }}" @class(['tab', 'tab-active' => $category === $c->value])>{{ $c->label() }}</a>
        @endforeach
    </nav>

    <div class="space-y-4">
        @forelse ($notices as $notice)
            <a href="{{ route('notices.show', $notice) }}" @class([
                'card block p-5 transition hover:shadow-md',
                'border-l-4 border-l-rose-500' => $notice->isUrgent(),
                'border-l-4 border-l-amber-400' => $notice->priority->value === 'important',
            ])>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($notice->is_pinned)
                        <x-badge tone="violet"><x-icon name="pin" class="size-3" /> Pinned</x-badge>
                    @endif
                    @if ($notice->priority->value !== 'normal')
                        <x-status :value="$notice->priority" />
                    @endif
                    <x-badge>{{ $notice->category->label() }}</x-badge>
                    @if ($notice->isNew())
                        <x-badge tone="brand">New</x-badge>
                    @endif
                    <span class="ml-auto text-xs text-slate-400">{{ $notice->published_at->format('j M Y, g:i A') }}</span>
                </div>
                <h2 class="mt-3 text-lg font-semibold text-slate-900">{{ $notice->title }}</h2>
                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $notice->body }}</p>
            </a>
        @empty
            <div class="card"><x-empty icon="megaphone" title="No notices here">Nothing has been published in this category.</x-empty></div>
        @endforelse
    </div>

    <div class="mt-6">{{ $notices->links() }}</div>
</x-layouts.app>
