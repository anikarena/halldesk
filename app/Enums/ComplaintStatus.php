<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ComplaintStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'red',
            self::InProgress => 'blue',
            self::Resolved => 'green',
        };
    }
}
