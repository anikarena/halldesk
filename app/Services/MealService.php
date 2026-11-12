<?php

namespace App\Services;

use App\Enums\Meal;
use App\Exceptions\HallException;
use App\Models\Allocation;
use App\Models\MealOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dining rules.
 *
 *  - Every resident gets lunch and dinner by default (meal ON).
 *  - A student can turn a meal OFF, only rows for OFF meals are stored.
 *  - Meals of a date are locked at the cutoff time on the day before
 *    (default 10:00 PM), so the dining manager knows how much to cook.
 */
class MealService
{
    /**
     * The last moment the meals of $date can be changed.
     */
    public function cutoffFor(CarbonInterface $date): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) setting('meal_cutoff_time', '22:00')));

        return CarbonImmutable::parse($date)->startOfDay()->subDay()->setTime($hour, $minute);
    }

    public function isChangeable(CarbonInterface $date): bool
    {
        return now()->lt($this->cutoffFor($date));
    }

    /**
     * The first date that is not locked yet (usually tomorrow, or the day after once the cutoff has passed).
     */
    public function firstChangeableDate(): CarbonImmutable
    {
        $date = CarbonImmutable::today();
        while (! $this->isChangeable($date)) {
            $date = $date->addDay();
        }

        return $date;
    }

    /**
     * Turn one meal of one date on or off.
     */
    public function set(User $student, CarbonInterface $date, Meal $meal, bool $on): void
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $this->assertCanChange($student, $date);

        if ($on) {
            MealOff::query()->where('user_id', $student->id)->where('date', $date->toDateString())->where('meal', $meal)->delete();
        } else {
            // The unique index (user, date, meal) makes a double click harmless
            MealOff::query()->insertOrIgnore([
                'user_id' => $student->id,
                'date' => $date->toDateString(),
                'meal' => $meal->value,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Turn the given meals on or off for every date in a range (for example a
     * trip home). Locked dates are skipped. Returns how many dates changed.
     *
     * @param  list<Meal>  $meals
     */
    public function setRange(User $student, CarbonInterface $from, CarbonInterface $to, array $meals, bool $on): int
    {
        // All days change, or none do
        return DB::transaction(function () use ($student, $from, $to, $meals, $on) {
            $changed = 0;
            foreach (CarbonPeriod::create($from, $to) as $day) {
                $day = CarbonImmutable::parse($day);
                if (! $this->isChangeable($day) || ! $this->isResidentOn($student, $day)) {
                    continue;
                }
                foreach ($meals as $meal) {
                    $this->set($student, $day, $meal, $on);
                }
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Off meals of one student between two dates, keyed "Y-m-d|meal".
     *
     * @return array<string, true>
     */
    public function offMap(User $student, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->keyOffs(
            MealOff::query()->where('user_id', $student->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->get()
        );
    }

    /**
     * @param  Collection<int, MealOff>  $offs
     * @return array<string, true>
     */
    public function keyOffs(Collection $offs): array
    {
        $map = [];
        foreach ($offs as $off) {
            $map[$off->date->toDateString().'|'.$off->meal->value] = true;
        }

        return $map;
    }

    /**
     * Every date between $from and $to on which the student held a seat.
     *
     * @param  Collection<int, Allocation>  $allocations
     * @return list<string> dates as Y-m-d
     */
    public function residentDays(Collection $allocations, CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        foreach ($allocations as $allocation) {
            $start = $allocation->start_date->max($from);
            $end = ($allocation->end_date ?? $to)->min($to);
            if ($start->gt($end)) {
                continue;
            }
            foreach (CarbonPeriod::create($start, $end) as $day) {
                $days[$day->toDateString()] = true;
            }
        }

        return array_keys($days);
    }

    /**
     * Count lunches and dinners taken on the given resident days.
     *
     * @param  list<string>  $days
     * @param  array<string, true>  $offMap
     * @return array{lunch: int, dinner: int}
     */
    public function countMeals(array $days, array $offMap): array
    {
        $counts = ['lunch' => 0, 'dinner' => 0];
        foreach ($days as $day) {
            foreach (Meal::cases() as $meal) {
                if (! isset($offMap[$day.'|'.$meal->value])) {
                    $counts[$meal->value]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Meals of one student from the 1st of this month up to $until.
     *
     * @return array{lunch: int, dinner: int, cost: int}
     */
    public function monthToDate(User $student, ?CarbonInterface $until = null): array
    {
        $from = CarbonImmutable::today()->startOfMonth();
        $until = CarbonImmutable::parse($until ?? today());

        $allocations = Allocation::query()->where('user_id', $student->id)
            ->overlapping($from->toDateString(), $until->toDateString())->get();
        $counts = $this->countMeals(
            $this->residentDays($allocations, $from, $until),
            $this->offMap($student, $from, $until),
        );

        $counts['cost'] = $counts['lunch'] * setting('lunch_rate') + $counts['dinner'] * setting('dinner_rate');

        return $counts;
    }

    /**
     * How many residents eat on a date, for the dining manager.
     *
     * @return array{residents: int, lunch: int, dinner: int}
     */
    public function headCount(CarbonInterface $date): array
    {
        $day = CarbonImmutable::parse($date)->toDateString();
        $residentIds = Allocation::query()->overlapping($day, $day)->distinct()->pluck('user_id');

        $offs = MealOff::query()->whereDate('date', $day)->whereIn('user_id', $residentIds)
            ->selectRaw('meal, count(*) as total')->groupBy('meal')->pluck('total', 'meal');

        $residents = $residentIds->count();

        return [
            'residents' => $residents,
            'lunch' => $residents - (int) ($offs[Meal::Lunch->value] ?? 0),
            'dinner' => $residents - (int) ($offs[Meal::Dinner->value] ?? 0),
        ];
    }

    public function isResidentOn(User $student, CarbonInterface $date): bool
    {
        $day = CarbonImmutable::parse($date)->toDateString();

        return Allocation::query()->where('user_id', $student->id)->overlapping($day, $day)->exists();
    }

    private function assertCanChange(User $student, CarbonImmutable $date): void
    {
        if (! $this->isChangeable($date)) {
            $cutoff = $this->cutoffFor($date);
            throw new HallException("Meals for {$date->format('D, j M')} are locked. Changes closed at {$cutoff->format('g:i A')} on {$cutoff->format('j M')}.");
        }

        $allocation = $student->activeAllocation()->first();
        if (! $allocation) {
            throw new HallException('Only residents with a seat can manage meals.');
        }
        if ($date->lt($allocation->start_date)) {
            throw new HallException('You can only change meals from the day your seat starts.');
        }
    }
}
