<?php

namespace App\Services\Assistant;

use App\Models\KnowledgeChunk;
use App\Models\Notice;
use App\Support\HallRules;
use Illuminate\Support\Collection;

/**
 * Retrieval for Ask AI (the "R" in RAG).
 *
 * Every rulebook section and every live notice is one passage. Each passage is
 * turned into a vector (embedding) once and stored in knowledge_chunks; a passage
 * is embedded again only when its text changes. A question is embedded the same
 * way and the passages closest to it (cosine similarity) are handed to the model.
 * Without embeddings (no key, API down) a plain keyword match is used instead.
 */
class Knowledge
{
    public function __construct(private Gemini $gemini, private HallRules $rules) {}

    /**
     * @return list<array{title: string, text: string, url: string, score: float}>
     */
    public function search(string $query, int $limit = 4): array
    {
        $chunks = $this->sync();

        $vector = null;
        if ($chunks->contains(fn (KnowledgeChunk $chunk) => $chunk->embedding)) {
            try {
                $vector = $this->gemini->embed([$query], 'RETRIEVAL_QUERY')[0];
            } catch (AssistantUnavailable) {
                // fall back to keywords below
            }
        }

        return $chunks
            ->map(fn (KnowledgeChunk $chunk) => [
                'chunk' => $chunk,
                'score' => $vector && $chunk->embedding
                    ? $this->cosine($vector, $chunk->embedding)
                    : $this->keywordScore($query, $chunk),
            ])
            ->filter(fn (array $hit) => $hit['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $hit) => [
                'title' => $hit['chunk']->title,
                'text' => $hit['chunk']->body,
                'url' => $hit['chunk']->url,
                'score' => round($hit['score'], 3),
            ])
            ->values()
            ->all();
    }

    /**
     * Brings the stored passages in line with the rulebook and the notices:
     * new or edited passages are embedded, removed ones are deleted.
     *
     * @return Collection<int, KnowledgeChunk>
     */
    public function sync(): Collection
    {
        $documents = collect($this->documents())->keyBy('key');
        KnowledgeChunk::query()->whereNotIn('key', $documents->keys())->delete();
        $stored = KnowledgeChunk::query()->get()->keyBy('key');

        $changed = $documents->filter(fn (array $doc) => ($stored[$doc['key']]->hash ?? null) !== $this->hash($doc))->values();
        if ($changed->isNotEmpty()) {
            $vectors = [];
            try {
                $vectors = $this->gemini->embed($changed->map(fn (array $doc) => $doc['title']."\n".$doc['body'])->all(), 'RETRIEVAL_DOCUMENT');
            } catch (AssistantUnavailable) {
                // saved without a vector, embedded on a later search
            }

            foreach ($changed as $i => $doc) {
                $stored[$doc['key']] = KnowledgeChunk::query()->updateOrCreate(['key' => $doc['key']], [
                    'title' => $doc['title'],
                    'body' => $doc['body'],
                    'url' => $doc['url'],
                    'hash' => isset($vectors[$i]) ? $this->hash($doc) : '',
                    'embedding' => $vectors[$i] ?? null,
                ]);
            }
        }

        return $stored->values();
    }

    /**
     * @return list<array{key: string, title: string, body: string, url: string}>
     */
    private function documents(): array
    {
        $documents = [];

        foreach ($this->rules->parse()['sections'] as $section) {
            $documents[] = [
                'key' => 'rules:'.$section['slug'],
                'title' => 'Hall rules, '.$section['title'],
                'body' => $section['body'],
                'url' => route('rules').'#'.$section['slug'],
            ];
        }

        foreach (Notice::query()->live()->latest('published_at')->limit(50)->get() as $notice) {
            $documents[] = [
                'key' => 'notice:'.$notice->id,
                'title' => 'Notice: '.$notice->title,
                'body' => $notice->body."\n(Published ".$notice->published_at->format('j M Y').')',
                'url' => route('notices.show', $notice),
            ];
        }

        return $documents;
    }

    /**
     * @param  array{title: string, body: string}  $doc
     */
    private function hash(array $doc): string
    {
        return sha1(config('hall.ai.embedding_model').'|'.$doc['title'].'|'.$doc['body']);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        $dot = $normA = $normB = 0.0;
        foreach ($a as $i => $value) {
            $dot += $value * ($b[$i] ?? 0);
            $normA += $value * $value;
            $normB += ($b[$i] ?? 0) ** 2;
        }

        return $normA > 0 && $normB > 0 ? $dot / sqrt($normA * $normB) : 0.0;
    }

    /**
     * Share of the question's words found in the passage, title words count double.
     */
    private function keywordScore(string $query, KnowledgeChunk $chunk): float
    {
        $words = array_unique(array_filter(
            preg_split('/[^a-z0-9]+/', strtolower($query)) ?: [],
            fn (string $word) => strlen($word) >= 3 && ! in_array($word, ['the', 'and', 'for', 'can', 'what', 'how', 'when', 'are', 'you', 'does', 'with'], true),
        ));
        if ($words === []) {
            return 0.0;
        }

        $title = strtolower($chunk->title);
        $body = strtolower($chunk->body);
        $score = 0;
        foreach ($words as $word) {
            $score += (str_contains($title, $word) ? 2 : 0) + (str_contains($body, $word) ? 1 : 0);
        }

        return $score / (3 * count($words));
    }
}
