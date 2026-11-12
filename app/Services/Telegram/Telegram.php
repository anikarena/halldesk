<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Small client for the Telegram Bot API. Messages use HTML formatting, so
 * any text from the database must go through e() first.
 */
class Telegram
{
    public function enabled(): bool
    {
        return filled(config('hall.telegram.token'));
    }

    /**
     * The bot's @username, for t.me links. Asked from Telegram once and kept.
     */
    public function username(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return Cache::rememberForever('telegram.username.'.sha1(config('hall.telegram.token')), function () {
            return $this->call('getMe')['username'] ?? null;
        });
    }

    /**
     * @param  list<list<array{text: string, callback_data?: string, url?: string}>>  $buttons  rows of inline buttons
     * @return array<string, mixed>|null the sent message
     */
    public function send(string $chatId, string $html, array $buttons = []): ?array
    {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            ...($buttons ? ['reply_markup' => ['inline_keyboard' => $buttons]] : []),
        ]);
    }

    public function edit(string $chatId, int $messageId, string $html): void
    {
        $this->call('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }

    public function answerCallback(string $callbackId, string $text = ''): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    /**
     * Long polling: waits up to $timeout seconds for new updates. Null when Telegram could not be reached.
     *
     * @return list<array<string, mixed>>|null
     */
    public function updates(int $offset, int $timeout = 25): ?array
    {
        return $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => ['message', 'callback_query'],
        ], $timeout + 10);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function call(string $method, array $params = [], int $timeout = 15): mixed
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            return Http::timeout($timeout)
                ->connectTimeout(10)
                ->post('https://api.telegram.org/bot'.config('hall.telegram.token').'/'.$method, $params)
                ->throw()
                ->json('result');
        } catch (ConnectionException|RequestException $e) {
            // Never log the URL: it contains the bot token
            $status = $e instanceof RequestException ? $e->response->status() : 'no connection';
            Log::warning("Telegram {$method} failed ({$status})", [
                'description' => $e instanceof RequestException ? $e->response->json('description') : null,
            ]);

            return null;
        }
    }
}
