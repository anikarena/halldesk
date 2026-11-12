<x-layouts.app title="Meals">
    <x-page-header title="Meals" :description="'Every meal is ON unless you turn it off. Lunch '.taka($lunchRate).', dinner '.taka($dinnerRate).' per meal.'" />

    <x-alert tone="info" icon="clock" class="mb-6" :title="'Next change deadline: '.$nextCutoff->format('g:i A, l j F')">
        Meals for {{ $nextOpen->format('l, j F') }} can be changed until then. After the deadline the kitchen starts planning and the day is locked.
    </x-alert>

    <section class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Lunches this month" :value="$month['lunch']" icon="sun" tone="amber" :hint="'Up to today, '.taka($month['lunch'] * $lunchRate)" />
        <x-stat label="Dinners this month" :value="$month['dinner']" icon="moon" tone="violet" :hint="'Up to today, '.taka($month['dinner'] * $dinnerRate)" />
        <x-stat label="Meal cost so far" :value="taka($month['cost'])" icon="receipt" hint="Added to your monthly hall bill" />
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Next 14 days</h2>
                <span class="flex items-center gap-3 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-500"></span>On</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-slate-300"></span>Off</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="lock" class="size-3" />Locked</span>
                </span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($days as $day)
                    <li @class(['grid items-center gap-3 px-5 py-3 sm:grid-cols-[10rem_1fr_1fr]', 'bg-slate-50/70' => ! $day['changeable']])>
                        <div class="flex items-center gap-3">
                            <span @class([
                                'grid size-11 shrink-0 place-items-center rounded-lg text-center leading-none',
                                'bg-brand-700 text-white' => $day['date']->isToday(),
                                'bg-white ring-1 ring-slate-200' => ! $day['date']->isToday(),
                            ])>
                                <span>
                                    <span class="block text-[10px] font-semibold uppercase">{{ $day['date']->format('M') }}</span>
                                    <span class="block text-base font-bold">{{ $day['date']->format('j') }}</span>
                                </span>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">{{ $day['date']->isToday() ? 'Today' : ($day['date']->isTomorrow() ? 'Tomorrow' : $day['date']->format('l')) }}</span>
                                <span class="block text-xs text-slate-500">
                                    @if ($day['changeable'])
                                        Change by {{ $day['cutoff']->format('g:i A, j M') }}
                                    @else
                                        Locked
                                    @endif
                                </span>
                            </span>
                        </div>
                        @foreach (\App\Enums\Meal::cases() as $meal)
                            @include('partials.meal-toggle', ['date' => $day['date'], 'meal' => $meal, 'on' => $day[$meal->value], 'canChange' => $day['changeable']])
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="space-y-6">
            <form method="POST" action="{{ route('student.meals.range') }}" class="card" x-data="{ action: @js(old('action', 'off')) }">
                @csrf
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Going home?</h2>
                        <p class="text-xs text-slate-500">Change many days at once, up to 60 days ahead.</p>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <x-input name="from" type="date" label="From" required :value="$nextOpen->toDateString()" :min="$nextOpen->toDateString()" />
                        <x-input name="to" type="date" label="To" required :value="$nextOpen->addDays(2)->toDateString()" :min="$nextOpen->toDateString()" />
                    </div>

                    <fieldset>
                        <legend class="label">Meals</legend>
                        <div class="flex gap-4 text-sm">
                            @foreach (\App\Enums\Meal::cases() as $meal)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="meals[]" value="{{ $meal->value }}" class="checkbox" @checked(in_array($meal->value, old('meals', ['lunch', 'dinner'])))>
                                    {{ $meal->label() }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="label">Turn them</legend>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['off' => 'Off', 'on' => 'Back on'] as $value => $label)
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium"
                                       x-bind:class="action === '{{ $value }}' ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-500' : 'border-slate-200 text-slate-600'">
                                    <input type="radio" name="action" value="{{ $value }}" class="sr-only" x-model="action"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">
                    <button type="submit" class="btn btn-primary w-full">Apply to these dates</button>
                    <p class="mt-2 text-center text-xs text-slate-500">Locked dates are skipped automatically.</p>
                </div>
            </form>

            <div class="card p-5 text-sm text-slate-600">
                <p class="font-semibold text-slate-800">Rules</p>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>Meals of a day lock at {{ \Carbon\Carbon::createFromFormat('H:i', setting('meal_cutoff_time'))->format('g:i A') }} the night before.</li>
                    <li>A meal that is ON is charged even if you do not eat it.</li>
                    <li>The total is added to your monthly hall bill.</li>
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
