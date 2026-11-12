<?php

namespace App\Services\Assistant;

/**
 * What the assistant says back after one message or one Confirm / Cancel.
 */
class Turn
{
    /**
     * @param  list<array{label: string, url: string}>  $links
     * @param  array{id: string, summary: string}|null  $pending
     */
    public function __construct(
        public string $text,
        public array $links = [],
        public ?array $pending = null,
    ) {}

    /**
     * @return array{text: string, links: list<array{label: string, url: string}>, pending: array{id: string, summary: string}|null}
     */
    public function toArray(): array
    {
        return ['text' => $this->text, 'links' => $this->links, 'pending' => $this->pending];
    }
}
