<?php

namespace App\Services\Telegram;

use App\Enums\Meal;
use App\Models\Allocation;
use App\Models\MealOff;
use App\Services\MealService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The lunch or dinner list the bot sends to the hall office: how many eat,
 * and who has turned the meal off (by seat, so the dining staff can check).
 */
class MealList
{
    private const MAX_NAMES = 40;

    public function __construct(private MealService $meals) {}

    /**
     * The coming meal: lunch until 3 PM, dinner until 10 PM, then tomorrow's lunch.
     */
    public function next(): string
    {
        $hour = now()->hour;

        return match (true) {
            $hour < 15 => $this->message(Meal::Lunch, today()),
            $hour < 22 => $this->message(Meal::Dinner, today()),
            default => $this->message(Meal::Lunch, today()->addDay()),
        };
    }

    public function message(Meal $meal, CarbonInterface $date): string
    {
        $day = CarbonImmutable::parse($date);
        $count = $this->meals->headCount($day);

        $seats = Allocation::query()->overlapping($day->toDateString(), $day->toDateString())
            ->with('seat.room')
            ->get()
            ->keyBy('user_id');
        $off = MealOff::query()
            ->whereDate('date', $day->toDateString())
            ->where('meal', $meal->value)
            ->whereIn('user_id', $seats->keys())
            ->with('user')
            ->get()
            ->map(fn (MealOff $row) => ['seat' => $seats[$row->user_id]->seat->code(), 'name' => $row->user->name])
            ->sortBy('seat', SORT_NATURAL)
            ->values();

        $lines = [
            '<b>'.$meal->label().', '.$day->format('D, j M').'</b>',
            $count[$meal->value].' of '.$count['residents'].' residents eat. '.$off->count().' off.',
        ];

        if ($off->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '<b>Off</b>';
            foreach ($off->take(self::MAX_NAMES) as $row) {
                $lines[] = e($row['seat']).'  '.e($row['name']);
            }
            if ($off->count() > self::MAX_NAMES) {
                $lines[] = 'and '.($off->count() - self::MAX_NAMES).' more';
            }
        }

        if (! $this->meals->isChangeable($day)) {
            $lines[] = '';
            $lines[] = 'This list is final.';
        }

        // Telegram only takes real web addresses, so no link on a local copy
        if (str_starts_with((string) config('app.url'), 'https://')) {
            $lines[] = '<a href="'.e(route('manage.meals.index', ['date' => $day->toDateString()])).'">Open the full meal sheet</a>';
        }

        return implode("\n", $lines);
    }
}
