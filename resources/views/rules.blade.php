<x-layouts.app :title="$rules['title']">
    <x-page-header :title="$rules['title']" :description="setting('hall_name').', '.setting('university_name')">
        <x-slot:actions>
            <a href="{{ route('assistant') }}" class="btn btn-secondary"><x-icon name="sparkle" class="size-4" /> Ask AI about the rules</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-8 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <nav class="hidden lg:block" aria-label="Sections">
            <ul class="sticky top-28 space-y-1 text-sm">
                @foreach ($rules['sections'] as $section)
                    <li><a href="#{{ $section['slug'] }}" class="block rounded-lg px-3 py-1.5 text-slate-600 hover:bg-cream hover:text-slate-900">{{ $section['title'] }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="max-w-3xl space-y-4">
            @if ($rules['intro'])
                <p class="text-slate-600">{{ $rules['intro'] }}</p>
            @endif
            @foreach ($rules['sections'] as $section)
                <section id="{{ $section['slug'] }}" class="card scroll-mt-28 p-6">
                    <h2 class="card-title">{{ $section['title'] }}</h2>
                    <div class="rules-body mt-3">{!! Str::markdown($section['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
