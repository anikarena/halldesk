<ul class="divide-y divide-slate-100">
    @forelse ($notices as $notice)
        <li>
            <a href="{{ route('notices.show', $notice) }}" @class([
                'flex gap-3 px-5 py-3.5 hover:bg-slate-50',
                'bg-rose-50/60 hover:bg-rose-50' => $notice->isUrgent(),
            ])>
                <span @class([
                    'mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg',
                    'bg-rose-600 text-white' => $notice->isUrgent(),
                    'bg-amber-100 text-amber-700' => $notice->priority->value === 'important',
                    'bg-slate-100 text-slate-500' => $notice->priority->value === 'normal',
                ])>
                    <x-icon :name="$notice->is_pinned ? 'pin' : 'megaphone'" class="size-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-slate-800">{{ $notice->title }}</span>
                        @if ($notice->priority->value !== 'normal')
                            <x-status :value="$notice->priority" />
                        @endif
                        @if ($notice->isNew())
                            <x-badge tone="brand">New</x-badge>
                        @endif
                    </span>
                    <span class="mt-0.5 line-clamp-2 block text-sm text-slate-500">{{ $notice->body }}</span>
                    <span class="mt-1 block text-xs text-slate-400">{{ $notice->category->label() }}, {{ $notice->published_at->format('j M Y') }}</span>
                </span>
            </a>
        </li>
    @empty
        <li><x-empty icon="megaphone" title="No notices right now" /></li>
    @endforelse
</ul>
