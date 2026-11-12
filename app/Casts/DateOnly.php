<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A calendar date stored as plain "Y-m-d" and read back as CarbonImmutable.
 *
 * Laravel's built-in date cast writes "Y-m-d 00:00:00". On SQLite that text
 * does not equal "Y-m-d", so lookups like where('date', '2026-11-07') or a
 * whereBetween ending on the last day of a month silently miss rows. Storing
 * one format everywhere keeps comparisons and unique indexes reliable.
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->toDateString();
    }
}
