<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum NoticePriority: string
{
    use HasOptions;

    case Normal = 'normal';
    case Important = 'important';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Important => 'Important',
            self::Urgent => 'Urgent',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::Important => 'amber',
            self::Urgent => 'red',
        };
    }

    /**
     * Sort weight, higher comes first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Urgent => 3,
            self::Important => 2,
            self::Normal => 1,
        };
    }
}
