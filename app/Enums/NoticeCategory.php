<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum NoticeCategory: string
{
    use HasOptions;

    case General = 'general';
    case Dining = 'dining';
    case Academic = 'academic';
    case Maintenance = 'maintenance';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Dining => 'Dining',
            self::Academic => 'Academic',
            self::Maintenance => 'Maintenance',
            self::Event => 'Event',
        };
    }
}
