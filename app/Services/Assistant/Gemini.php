<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Small client for the Google Gemini API: one chat turn with function calling,
 * and text embeddings for the rulebook search. Settings live in config/hall.php.
 */
class Gemini
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    private const DIMENSIONS = 768;

    public function enabled(): bool
    {
        return filled(config('hall.ai.key'));
    }

    /**
     * Ask the model for its next turn. Returns the content to keep in the history,
     * unchanged, because Gemini needs its own parts (with signatures) sent back.
     *
     * @param  list<array<string, mixed>>  $contents
     * @param  list<array<string, mixed>>  $functions
     * @return array{role: string, parts: list<array<string, mixed>>}
     */
    public function generate(string $system, array $contents, array $functions): array
    {
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $this->withObjectArgs($contents),
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 1024],
        ];
        if ($functions !== []) {
            $payload['tools'] = [['functionDeclarations' => $functions]];
        }

        $data = $this->post(config('hall.ai.model').':generateContent', $payload);
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        if ($parts === []) {
            throw new AssistantUnavailable('The AI gave an empty answer. Please try again.');
        }

        return ['role' => 'model', 'parts' => $parts];
    }

    /**
     * A tool call without arguments comes back as "args": {}, but PHP (and the
     * JSON session) turn that into an empty array, and Gemini refuses "args": [].
     *
     * @param  list<array<string, mixed>>  $contents
     * @return list<array<string, mixed>>
     */
    private function withObjectArgs(array $contents): array
    {
        foreach ($contents as &$content) {
            foreach ($content['parts'] as &$part) {
                if (isset($part['functionCall']) && empty($part['functionCall']['args'])) {
                    $part['functionCall']['args'] = new stdClass;
                }
            }
            unset($part);
        }

        return $contents;
    }

    /**
     * @param  list<string>  $texts
     * @param  string  $task  RETRIEVAL_DOCUMENT for stored text, RETRIEVAL_QUERY for a question
     * @return list<list<float>>
     */
    public function embed(array $texts, string $task): array
    {
        $model = config('hall.ai.embedding_model');
        $vectors = [];

        foreach (array_chunk($texts, 100) as $chunk) {
            $data = $this->post($model.':batchEmbedContents', [
                'requests' => array_map(fn (string $text) => [
                    'model' => 'models/'.$model,
                    'content' => ['parts' => [['text' => $text]]],
                    'taskType' => $task,
                    'outputDimensionality' => self::DIMENSIONS,
                ], $chunk),
            ]);
            foreach ($data['embeddings'] ?? [] as $embedding) {
                $vectors[] = $embedding['values'];
            }
        }

        if (count($vectors) !== count($texts)) {
            throw new AssistantUnavailable('The AI search is not answering right now.');
        }

        return $vectors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        if (! $this->enabled()) {
            throw new AssistantUnavailable('Ask AI is switched off on this server.');
        }

        try {
            return Http::withHeaders(['x-goog-api-key' => config('hall.ai.key')])
                ->connectTimeout(10)
                ->timeout(45)
                ->retry(2, 700, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()))
                ->post(self::BASE.$path, $payload)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Gemini request failed', ['path' => $path, 'error' => $e->getMessage()]);

            $busy = $e instanceof RequestException && $e->response->status() === 429;
            throw new AssistantUnavailable($busy
                ? 'Ask AI is busy right now. Please try again in a minute.'
                : 'Ask AI is not answering right now. Please try again in a minute.', 0, $e);
        }
    }
}
