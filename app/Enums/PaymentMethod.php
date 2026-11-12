<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentMethod: string
{
    use HasOptions;

    case Cash = 'cash';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash at hall office',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Rocket => 'Rocket',
            self::Bank => 'Bank transfer',
        };
    }

    /**
     * Methods a student can use from the website. Cash is recorded by staff.
     *
     * @return array<string, string>
     */
    public static function online(): array
    {
        $options = self::options();
        unset($options[self::Cash->value]);

        return $options;
    }
}
