<?php

namespace App\Services\Assistant;

/**
 * One user's chat with the assistant, kept in their session.
 *
 *  - contents: the history in Gemini's format, sent with every request
 *  - messages: what the chat page shows (questions, answers, links)
 *  - pending:  a change waiting for the user's Confirm or Cancel
 */
class Conversation
{
    private const KEEP_QUESTIONS = 8;

    private const KEEP_MESSAGES = 40;

    /**
     * @param  list<array<string, mixed>>  $contents
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>|null  $pending
     */
    public function __construct(
        public array $contents = [],
        public array $messages = [],
        public ?array $pending = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['contents'] ?? [], $data['messages'] ?? [], $data['pending'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['contents' => $this->contents, 'messages' => $this->messages, 'pending' => $this->pending];
    }

    /**
     * Keep the request small: only the last few questions and everything after them.
     * Cutting at a question never splits a tool call from its answer.
     */
    public function trim(): void
    {
        $questions = [];
        foreach ($this->contents as $i => $content) {
            if ($content['role'] === 'user' && isset($content['parts'][0]['text'])) {
                $questions[] = $i;
            }
        }
        if (count($questions) > self::KEEP_QUESTIONS) {
            $this->contents = array_slice($this->contents, $questions[count($questions) - self::KEEP_QUESTIONS]);
        }

        $this->messages = array_slice($this->messages, -self::KEEP_MESSAGES);
    }
}
