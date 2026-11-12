<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ComplaintCategory: string
{
    use HasOptions;

    case Electricity = 'electricity';
    case Water = 'water';
    case Wifi = 'wifi';
    case Furniture = 'furniture';
    case Cleanliness = 'cleanliness';
    case Security = 'security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => 'Electricity',
            self::Water => 'Water and plumbing',
            self::Wifi => 'Internet / WiFi',
            self::Furniture => 'Furniture',
            self::Cleanliness => 'Cleanliness',
            self::Security => 'Security',
            self::Other => 'Other',
        };
    }
}
