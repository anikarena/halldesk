<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Role: string
{
    use HasOptions;

    case Admin = 'admin';
    case Staff = 'staff';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Provost / Admin',
            self::Staff => 'Hall Staff',
            self::Student => 'Student',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Admin => 'violet',
            self::Staff => 'blue',
            self::Student => 'gray',
        };
    }
}
