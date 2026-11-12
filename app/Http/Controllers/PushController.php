<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\Push\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turns push alerts on and off for the device that is asking.
 */
class PushController extends Controller
{
    /**
     * The browser sends its subscription after the user allowed notifications,
     * and again on later visits. A device follows the account signed in on it.
     */
    public function store(Request $request, WebPush $push): JsonResponse
    {
        abort_unless($push->enabled(), 503, 'Push alerts are not set up on this server.');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
            ],
        );

        return response()->json(['on' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        $request->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hash($data['endpoint']))->delete();

        return response()->json(['on' => false]);
    }
}
