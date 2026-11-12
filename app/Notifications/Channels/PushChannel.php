<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\HallAlert;
use App\Services\Push\WebPush;

/**
 * Pushes a HallAlert to the user's devices. Tapping the alert goes through
 * notifications.open, so it is marked read like a tap in the bell list.
 */
class PushChannel
{
    public function __construct(private WebPush $push) {}

    public function send(object $notifiable, HallAlert $alert): void
    {
        if (! $notifiable instanceof User || ! $this->push->enabled()) {
            return;
        }

        $payload = [
            'title' => $alert->title,
            'body' => $alert->message,
            'url' => route('notifications.open', $alert->id, absolute: false),
            'tag' => $alert->id,
        ];
        $send = fn () => $this->push->send($notifiable, $payload);

        // A page must not wait for the push service; commands (the Telegram bot) send at once
        app()->runningInConsole() ? $send() : defer($send);
    }
}
