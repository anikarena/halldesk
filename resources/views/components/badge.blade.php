@props(['tone' => 'gray', 'dot' => false])
<span {{ $attributes->merge(['class' => 'badge badge-'.$tone]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full bg-current"></span>
    @endif
    {{ $slot }}
</span>
