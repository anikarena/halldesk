<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\Knowledge;
use App\Services\Assistant\Tool;

class SearchHallRules extends Tool
{
    public function __construct(private Knowledge $knowledge) {}

    public function name(): string
    {
        return 'search_hall_rules';
    }

    public function description(): string
    {
        return 'Search the hall rulebook and the current notices. Use it for every question about rules, conduct, '
            .'return time, cooking, rent dates, payment methods, how meals and complaints work, applying for a seat, '
            .'leaving the hall and hall notices. Returns the best matching passages with their titles.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'What to look for, as short English keywords, for example "cooking in room" or "rent payment deadline".'],
            ],
            'required' => ['query'],
        ];
    }

    public function run(User $user, array $args): array
    {
        $hits = $this->knowledge->search((string) ($args['query'] ?? ''));

        // Link the best passage, and the next one only when it is almost as close
        $top = $hits[0]['score'] ?? 0;
        $linked = array_slice(array_filter($hits, fn (array $hit) => $hit['score'] >= $top - 0.03), 0, 2);

        return [
            'passages' => array_map(fn (array $hit) => ['title' => $hit['title'], 'text' => $hit['text']], $hits),
            'note' => $hits === [] ? 'Nothing matched. The rulebook does not cover this.' : null,
            '_links' => array_map(fn (array $hit) => ['label' => $hit['title'], 'url' => $hit['url']], $linked),
        ];
    }
}
