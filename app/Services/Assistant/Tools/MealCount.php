<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\Tool;
use App\Services\MealService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class MealCount extends Tool
{
    public function __construct(private MealService $meals) {}

    public function name(): string
    {
        return 'meal_count';
    }

    public function description(): string
    {
        return 'How many lunches and dinners to cook on a date, and whether the list is final (locked) yet.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => 'Y-m-d. Default today.'],
            ],
        ];
    }

    public function allows(User $user): bool
    {
        return $user->isManager();
    }

    public function run(User $user, array $args): array
    {
        $data = Validator::make($args, ['date' => ['nullable', 'date_format:Y-m-d']])->validate();
        $date = CarbonImmutable::parse($data['date'] ?? today());

        return [
            'date' => $date->format('D, j M Y'),
            ...$this->meals->headCount($date),
            'final' => ! $this->meals->isChangeable($date),
            'changes_close' => $this->meals->cutoffFor($date)->format('D, j M, g:i A'),
            '_links' => [['label' => 'Daily meal sheet', 'url' => route('manage.meals.index')]],
        ];
    }
}
