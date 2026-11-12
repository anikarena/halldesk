<?php

namespace App\Http\Controllers\Manage;

use App\Enums\Meal;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\MealOff;
use App\Services\MealService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dining sheet for the kitchen and monthly meal totals.
 */
class MealController extends Controller
{
    public function __construct(private MealService $meals) {}

    public function index(Request $request): View
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($request->query('date', today()->toDateString()));
        $day = $date->toDateString();

        $offs = MealOff::query()->whereDate('date', $day)
            ->with('user.profile', 'user.activeAllocation.seat.room')
            ->get()
            ->groupBy(fn (MealOff $off) => $off->meal->value);

        return view('manage.meals.index', [
            'date' => $date,
            'count' => $this->meals->headCount($date),
            'lunchOff' => $offs->get(Meal::Lunch->value, collect()),
            'dinnerOff' => $offs->get(Meal::Dinner->value, collect()),
            'locked' => ! $this->meals->isChangeable($date),
            'cutoff' => $this->meals->cutoffFor($date),
            'week' => collect(range(0, 6))->map(fn ($i) => [
                'date' => today()->toImmutable()->addDays($i),
                'count' => $this->meals->headCount(today()->addDays($i)),
            ]),
        ]);
    }

    public function monthly(Request $request): View
    {
        $request->validate(['period' => ['nullable', 'date_format:Y-m']]);
        $period = $request->query('period', today()->format('Y-m'));

        $from = CarbonImmutable::createFromFormat('!Y-m', $period)->startOfMonth();
        $to = $from->endOfMonth()->startOfDay()->min(CarbonImmutable::today());

        $allocations = Allocation::query()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->with('user.profile', 'seat.room')
            ->get()
            ->groupBy('user_id');

        $offs = MealOff::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('user_id');

        $lunchRate = (int) setting('lunch_rate');
        $dinnerRate = (int) setting('dinner_rate');

        $rows = $allocations->map(function ($userAllocations, $userId) use ($from, $to, $offs, $lunchRate, $dinnerRate) {
            $days = $this->meals->residentDays($userAllocations, $from, $to);
            $counts = $this->meals->countMeals($days, $this->meals->keyOffs($offs->get($userId, collect())));

            return [
                'user' => $userAllocations->first()->user,
                'seat' => $userAllocations->sortByDesc('start_date')->first()->seat,
                'days' => count($days),
                'lunch' => $counts['lunch'],
                'dinner' => $counts['dinner'],
                'cost' => $counts['lunch'] * $lunchRate + $counts['dinner'] * $dinnerRate,
            ];
        })->sortBy(fn ($row) => $row['user']->name)->values();

        return view('manage.meals.monthly', [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'lunch' => $rows->sum('lunch'),
                'dinner' => $rows->sum('dinner'),
                'cost' => $rows->sum('cost'),
            ],
            'periods' => collect(range(0, 5))->map(fn ($i) => today()->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m')),
        ]);
    }
}
