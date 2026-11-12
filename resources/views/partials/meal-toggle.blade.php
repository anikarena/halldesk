{{-- One meal switch. $date (Carbon), $meal (App\Enums\Meal), $on (bool), $canChange (bool) --}}
<form method="POST" action="{{ route('student.meals.toggle') }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    <input type="hidden" name="meal" value="{{ $meal->value }}">
    <input type="hidden" name="on" value="{{ $on ? 0 : 1 }}">
    <button type="submit" @disabled(! $canChange)
        aria-label="{{ $meal->label() }} on {{ $date->format('j M') }} is {{ $on ? 'on' : 'off' }}{{ $canChange ? ', click to change' : ', locked' }}"
        @class([
            'flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition',
            'border-brand-200 bg-brand-50 text-brand-900' => $on,
            'border-slate-200 bg-white text-slate-500' => ! $on,
            'hover:border-brand-400 hover:shadow-xs' => $canChange,
            'cursor-not-allowed opacity-75' => ! $canChange,
        ])>
        <x-icon :name="$meal === \App\Enums\Meal::Lunch ? 'sun' : 'moon'" class="size-4" />
        <span class="flex-1 font-medium">{{ $meal->label() }}</span>
        @if ($canChange)
            {{-- Switch look --}}
            <span @class(['relative inline-flex h-5 w-9 shrink-0 rounded-full transition', 'bg-brand-600' => $on, 'bg-slate-300' => ! $on])>
                <span @class(['absolute top-0.5 size-4 rounded-full bg-white shadow transition-all', 'left-[18px]' => $on, 'left-0.5' => ! $on])></span>
            </span>
        @else
            <span class="text-xs font-bold">{{ $on ? 'ON' : 'OFF' }}</span>
            <x-icon name="lock" class="size-3.5 opacity-60" />
        @endif
    </button>
</form>
