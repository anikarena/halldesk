<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Meal: string
{
    use HasOptions;

    case Lunch = 'lunch';
    case Dinner = 'dinner';

    public function label(): string
    {
        return match ($this) {
            self::Lunch => 'Lunch',
            self::Dinner => 'Dinner',
        };
    }
}
