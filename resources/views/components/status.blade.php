{{-- Badge for any enum with label() and tone(), for example <x-status :value="$invoice->status" /> --}}
@props(['value'])
<span {{ $attributes->merge(['class' => 'badge badge-'.$value->tone()]) }}>
    <span class="size-1.5 rounded-full bg-current"></span>{{ $value->label() }}
</span>
