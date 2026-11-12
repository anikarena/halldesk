<?php

use App\Support\Settings;
use Carbon\CarbonImmutable;

if (! function_exists('setting')) {
    /**
     * Read one hall setting, see App\Support\Settings::DEFAULTS for the keys.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('taka')) {
    /**
     * Format a whole taka amount: 4350 becomes "৳4,350".
     */
    function taka(int|float|null $amount): string
    {
        $amount = (int) round($amount ?? 0);
        $sign = $amount < 0 ? '-' : '';

        return $sign.'৳'.number_format(abs($amount));
    }
}

if (! function_exists('month_label')) {
    /**
     * "2026-10" becomes "October 2026".
     */
    function month_label(?string $period): string
    {
        if (! $period) {
            return '';
        }

        return CarbonImmutable::createFromFormat('!Y-m', $period)->format('F Y');
    }
}
