<x-layouts.app :title="$notice->title">
    <x-page-header :title="$notice->title" :back="route('notices.index')" back-label="Notice board">
        @if (auth()->user()->isAdmin())
            <x-slot:actions>
                <a href="{{ route('manage.notices.edit', $notice) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Edit</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <article class="card max-w-3xl overflow-hidden">
        @if ($notice->isUrgent())
            <div class="flex items-center gap-2 bg-rose-600 px-6 py-2.5 text-sm font-semibold text-white"><x-icon name="megaphone" class="size-4" /> Urgent notice</div>
        @endif
        <div class="p-6 sm:p-8">
            <div class="flex flex-wrap items-center gap-2">
                @if ($notice->is_pinned) <x-badge tone="violet"><x-icon name="pin" class="size-3" /> Pinned</x-badge> @endif
                @if ($notice->priority->value !== 'normal') <x-status :value="$notice->priority" /> @endif
                <x-badge>{{ $notice->category->label() }}</x-badge>
                @if ($notice->is_public) <x-badge tone="blue">Public</x-badge> @endif
                @unless ($notice->isLive()) <x-badge tone="amber">Not visible to students</x-badge> @endunless
            </div>
            <div class="mt-6 whitespace-pre-line text-[15px] leading-7 text-slate-700">{{ $notice->body }}</div>
            <div class="mt-8 border-t border-slate-100 pt-4 text-sm text-slate-500">
                Published {{ $notice->published_at->format('l, j F Y, g:i A') }}
                @if ($notice->creator) by {{ $notice->creator->name }} @endif
                @if ($notice->expires_at)<br>Visible until {{ $notice->expires_at->format('j F Y, g:i A') }} @endif
            </div>
        </div>
    </article>
</x-layouts.app>
