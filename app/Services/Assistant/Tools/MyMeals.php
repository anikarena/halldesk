<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\Tool;
use App\Services\MealService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Validator;

class MyMeals extends Tool
{
    public function __construct(private MealService $meals) {}

    public function name(): string
    {
        return 'my_meals';
    }

    public function description(): string
    {
        return 'The student\'s lunch and dinner (ON or OFF) for the coming days, whether each day can still be changed, '
            .'and this month\'s meal count and cost.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'description' => 'First date, Y-m-d. Default today.'],
                'days' => ['type' => 'integer', 'description' => 'How many days, 1 to 14. Default 7.'],
            ],
        ];
    }

    public function allows(User $user): bool
    {
        return $user->isResident();
    }

    public function run(User $user, array $args): array
    {
        $data = Validator::make($args, [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
        ])->validate();

        $from = CarbonImmutable::parse($data['from'] ?? today());
        $to = $from->addDays(($data['days'] ?? 7) - 1);
        $offs = $this->meals->offMap($user, $from, $to);

        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $day = CarbonImmutable::parse($day);
            $key = $day->toDateString();
            $days[] = [
                'date' => $day->format('D, j M'),
                'lunch' => isset($offs[$key.'|lunch']) ? 'OFF' : 'ON',
                'dinner' => isset($offs[$key.'|dinner']) ? 'OFF' : 'ON',
                'can_change' => $this->meals->isChangeable($day),
                'changes_close' => $this->meals->cutoffFor($day)->format('D, j M, g:i A'),
            ];
        }

        $month = $this->meals->monthToDate($user);

        return [
            'days' => $days,
            'this_month_so_far' => [
                'lunches' => $month['lunch'],
                'dinners' => $month['dinner'],
                'cost' => taka($month['cost']),
            ],
            'rates' => ['lunch' => taka((int) setting('lunch_rate')), 'dinner' => taka((int) setting('dinner_rate'))],
            '_links' => [['label' => 'Meals', 'url' => route('student.meals')]],
        ];
    }
}
