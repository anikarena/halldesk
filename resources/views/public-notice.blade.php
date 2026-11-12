<x-layouts.site :title="$notice->title">
    <article class="shell max-w-4xl pt-6" data-reveal>
        <a href="{{ route('home') }}#notices" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-brand-600"><x-icon name="arrow-left" class="size-4" /> All notices</a>
        <div class="mt-8 flex flex-wrap items-center gap-2">
            <span class="rounded-md bg-cream px-2 py-0.5 text-xs text-slate-700">{{ $notice->category->label() }}</span>
            @if ($notice->priority->value !== 'normal')<x-status :value="$notice->priority" />@endif
        </div>
        <h1 class="display mt-4 text-5xl sm:text-6xl">{{ $notice->title }}</h1>
        <p class="mt-4 flex items-center gap-1.5 text-sm text-slate-500"><x-icon name="clock" class="size-4" /> {{ $notice->published_at->format('l, j F Y') }}</p>
        <div class="mt-10 whitespace-pre-line border-t border-slate-200 pt-10 text-base leading-8 text-slate-700">{{ $notice->body }}</div>
    </article>
</x-layouts.site>
