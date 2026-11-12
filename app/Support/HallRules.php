<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The hall rulebook, kept as Markdown in resources/hall/rules.md so the hall
 * office can edit it. Shown on the Hall rules page and searched by Ask AI.
 *
 * Every "## " heading starts a section. Names in curly brackets such as
 * {lunch_rate} are filled in from Hall settings, HTML comments are dropped.
 */
class HallRules
{
    public function path(): string
    {
        return resource_path('hall/rules.md');
    }

    public function markdown(): string
    {
        $text = (string) file_get_contents($this->path());
        $text = (string) preg_replace('/<!--.*?-->/s', '', $text);

        return trim(strtr($text, $this->placeholders()));
    }

    /**
     * @return array{title: string, intro: string, sections: list<array{slug: string, title: string, body: string}>}
     */
    public function parse(): array
    {
        $parts = preg_split('/^## +/m', $this->markdown()) ?: [''];
        $head = (string) array_shift($parts);

        $sections = [];
        foreach ($parts as $part) {
            [$heading, $body] = array_pad(explode("\n", $part, 2), 2, '');
            $sections[] = ['slug' => Str::slug($heading), 'title' => trim($heading), 'body' => trim($body)];
        }

        return [
            'title' => preg_match('/^# +(.+)$/m', $head, $m) ? trim($m[1]) : 'Hall rules',
            'intro' => trim((string) preg_replace('/^# +.+$/m', '', $head)),
            'sections' => $sections,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        $day = (int) setting('bill_due_day', 10);
        $suffix = in_array($day % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$day % 10] ?? 'th');

        $values = [
            'hall_name' => setting('hall_name'),
            'university_name' => setting('university_name'),
            'contact_phone' => setting('contact_phone'),
            'contact_email' => setting('contact_email'),
            'lunch_rate' => taka((int) setting('lunch_rate')),
            'dinner_rate' => taka((int) setting('dinner_rate')),
            'meal_cutoff' => CarbonImmutable::createFromFormat('H:i', (string) setting('meal_cutoff_time', '22:00'))->format('g:i A'),
            'bill_due_day' => $day.$suffix,
            'electricity_charge' => taka((int) setting('electricity_charge')),
            'bkash_number' => setting('bkash_number'),
            'nagad_number' => setting('nagad_number'),
            'rocket_number' => setting('rocket_number'),
            'bank_account' => setting('bank_account'),
        ];

        $map = [];
        foreach ($values as $key => $value) {
            $map['{'.$key.'}'] = (string) $value;
        }

        return $map;
    }
}
