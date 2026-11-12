<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Hall wide settings (name, meal rates, payment numbers...).
 * Stored as key/value rows, cached, and merged over the defaults below.
 * Bound as a singleton, use the setting() helper to read a value.
 */
class Settings
{
    public const DEFAULTS = [
        // From cityuniversity.ac.bd (permanent campus contact), editable in Hall settings
        'hall_name' => 'Mockbul Hossain Hall',
        'university_name' => 'City University',
        'hall_address' => 'Khagan, Birulia, Savar, Dhaka-1340',
        'contact_phone' => '09643-234234',
        'contact_email' => 'info@cityuniversity.ac.bd',
        'lunch_rate' => '70',
        'dinner_rate' => '70',
        'meal_cutoff_time' => '22:00',
        // Added to every resident's monthly bill next to the seat rent
        'electricity_charge' => '100',
        'bill_due_day' => '10',
        'bkash_number' => '01700 000000',
        'nagad_number' => '01800 000000',
        'rocket_number' => '01900 0000000',
        'bank_account' => 'Sonali Bank, Campus Branch, A/C 0000 0000 0000',
    ];

    private const CACHE_KEY = 'hall.settings';

    /** @var array<string, string>|null */
    private ?array $values = null;

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->values === null) {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());
            $this->values = array_merge(self::DEFAULTS, array_filter($stored, fn ($v) => $v !== null));
        }

        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }
}
