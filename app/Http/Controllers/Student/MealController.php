<?php

namespace App\Http\Controllers\Student;

use App\Enums\Meal;
use App\Http\Controllers\Controller;
use App\Services\MealService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MealController extends Controller
{
    private const DAYS_SHOWN = 14;

    public function __construct(private MealService $meals) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $allocation = $user->activeAllocation()->first();

        if (! $allocation) {
            return redirect()->route('dashboard')->with('info', 'Meal booking opens once you have a seat in the hall.');
        }

        $from = CarbonImmutable::today();
        $to = $from->addDays(self::DAYS_SHOWN - 1);
        $offs = $this->meals->offMap($user, $from, $to);

        $days = collect(CarbonPeriod::create($from, $to))->map(function ($day) use ($offs, $allocation) {
            $day = CarbonImmutable::parse($day);
            $key = $day->toDateString();

            return [
                'date' => $day,
                'lunch' => ! isset($offs[$key.'|lunch']),
                'dinner' => ! isset($offs[$key.'|dinner']),
                'changeable' => $this->meals->isChangeable($day) && $day->gte($allocation->start_date),
                'cutoff' => $this->meals->cutoffFor($day),
            ];
        });

        $nextOpen = $this->meals->firstChangeableDate();

        return view('student.meals', [
            'days' => $days,
            'month' => $this->meals->monthToDate($user),
            'nextOpen' => $nextOpen,
            'nextCutoff' => $this->meals->cutoffFor($nextOpen),
            'lunchRate' => (int) setting('lunch_rate'),
            'dinnerRate' => (int) setting('dinner_rate'),
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'meal' => ['required', Rule::enum(Meal::class)],
            'on' => ['required', 'boolean'],
        ]);

        $date = CarbonImmutable::parse($data['date']);
        $meal = Meal::from($data['meal']);
        $on = (bool) $data['on'];

        $this->meals->set($request->user(), $date, $meal, $on);

        return back()->with('success', "{$meal->label()} on {$date->format('D, j M')} is now ".($on ? 'ON' : 'OFF').'.');
    }

    /**
     * Going home for a few days: switch meals off (or back on) for a date range.
     */
    public function range(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date', 'after_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:'.today()->addDays(60)->toDateString()],
            'meals' => ['required', 'array', 'min:1'],
            'meals.*' => [Rule::enum(Meal::class)],
            'action' => ['required', Rule::in(['on', 'off'])],
        ], [
            'to.before_or_equal' => 'You can plan meals up to 60 days ahead.',
            'meals.required' => 'Choose lunch, dinner or both.',
        ]);

        $changed = $this->meals->setRange(
            $request->user(),
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['to']),
            array_map(fn ($m) => Meal::from($m), $data['meals']),
            $data['action'] === 'on',
        );

        if ($changed === 0) {
            return back()->with('error', 'Nothing changed. The dates you chose are already locked.');
        }

        return back()->with('success', 'Meals turned '.strtoupper($data['action'])." for {$changed} ".str('day')->plural($changed).'. Locked dates were skipped.');
    }
}
