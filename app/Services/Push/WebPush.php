<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use App\Models\User;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush as Client;
use Throwable;

/**
 * Sends push alerts to the devices of a user (Web Push with VAPID keys).
 * A device that has gone away (app removed, permission taken back) is
 * forgotten the first time the push service says so.
 */
class WebPush
{
    public function enabled(): bool
    {
        return filled(config('hall.push.public_key')) && filled(config('hall.push.private_key'));
    }

    public function publicKey(): ?string
    {
        return $this->enabled() ? config('hall.push.public_key') : null;
    }

    /**
     * @param  array{title: string, body: string, url: string, tag?: string}  $alert
     * @return int devices the alert was handed to
     */
    public function send(User $user, array $alert): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $devices = $user->pushSubscriptions()->get();
        if ($devices->isEmpty()) {
            return 0;
        }

        try {
            $gone = $this->deliver($devices, json_encode($alert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            // A push is an extra, the notification is already in the bell list
            Log::warning('Push alert failed: '.$e->getMessage());

            return 0;
        }

        if ($gone) {
            PushSubscription::query()->whereIn('endpoint_hash', array_map(PushSubscription::hash(...), $gone))->delete();
        }

        return $devices->count() - count($gone);
    }

    /**
     * @param  Collection<int, PushSubscription>  $devices
     * @return list<string> endpoints that no longer exist
     */
    protected function deliver(Collection $devices, string $payload): array
    {
        $client = new Client(
            ['VAPID' => [
                'subject' => config('hall.push.subject'),
                'publicKey' => config('hall.push.public_key'),
                'privateKey' => config('hall.push.private_key'),
            ]],
            // The push service keeps an alert for a day when the phone is offline
            ['TTL' => 86400, 'urgency' => 'high'],
            new HttpClient(['timeout' => 10, 'connect_timeout' => 5]),
        );

        foreach ($devices as $device) {
            $client->queueNotification(Subscription::create([
                'endpoint' => $device->endpoint,
                'keys' => ['p256dh' => $device->p256dh, 'auth' => $device->auth],
            ]), $payload);
        }

        $gone = [];
        foreach ($client->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $gone[] = $report->getEndpoint();
            } elseif (! $report->isSuccess()) {
                // Never log the endpoint: whoever has it can push to that device
                Log::warning('Push alert rejected: '.$report->getReason());
            }
        }

        return $gone;
    }
}
