<?php

use App\Enums\Meal;
use App\Models\User;
use App\Services\Assistant\Knowledge;
use App\Services\Telegram\MealList;
use App\Services\Telegram\StaffBot;
use App\Services\Telegram\Telegram;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ask AI also does this on its own before a search, this just saves the first user the wait
Artisan::command('hall:knowledge', function (Knowledge $knowledge) {
    $chunks = $knowledge->sync();
    $this->info($chunks->count().' passages, '.$chunks->filter(fn ($chunk) => $chunk->embedding)->count().' with embeddings.');
})->purpose('Embed the hall rules and notices for Ask AI');

// Once per server. New keys make every device turn alerts on again, so keep the pair.
Artisan::command('push:keys', function () {
    try {
        $keys = VAPID::createVapidKeys();
    } catch (RuntimeException $e) {
        // PHP on Windows cannot make keys until it is told where its openssl.cnf is
        $this->error($e->getMessage().'. On Windows, set OPENSSL_CONF to the openssl.cnf in PHP\'s extras\ssl folder and run this again.');

        return 1;
    }
    $this->line('Add these two lines to .env:');
    $this->newLine();
    $this->line('HALL_PUSH_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('HALL_PUSH_PRIVATE_KEY='.$keys['privateKey']);
})->purpose('Make the key pair for push alerts');

// The hall office bot. Runs all the time (a systemd service on the server).
Artisan::command('telegram:poll {--once : Handle what is waiting and stop}', function (Telegram $telegram, StaffBot $bot) {
    if (! $telegram->enabled()) {
        $this->error('TELEGRAM_BOT_TOKEN is not set.');

        return 1;
    }

    $telegram->call('deleteWebhook');
    $this->info('Listening as @'.$telegram->username());
    $offset = (int) Cache::get('telegram.offset', 0);

    do {
        $updates = $telegram->updates($offset, $this->option('once') ? 0 : 25);
        if ($updates === null) {
            sleep(5);

            continue;
        }
        foreach ($updates as $update) {
            $offset = $update['update_id'] + 1;
            Cache::forever('telegram.offset', $offset);
            try {
                $bot->handle($update);
            } catch (Throwable $e) {
                report($e);
            }
        }
    } while (! $this->option('once'));

    return 0;
})->purpose('Run the hall office Telegram bot');

Artisan::command('telegram:meals {meal : lunch or dinner}', function (Telegram $telegram, MealList $list) {
    $meal = Meal::tryFrom((string) $this->argument('meal'));
    if (! $meal || ! $telegram->enabled()) {
        $this->error($meal ? 'TELEGRAM_BOT_TOKEN is not set.' : 'Use lunch or dinner.');

        return 1;
    }

    $text = $list->message($meal, today());
    $staff = User::query()->managers()->where('is_active', true)->whereNotNull('telegram_chat_id')->get();
    foreach ($staff as $user) {
        $telegram->send($user->telegram_chat_id, $text);
    }
    $this->info("{$meal->label()} list sent to {$staff->count()} hall office accounts.");

    return 0;
})->purpose('Send the lunch or dinner list to the hall office on Telegram');

Schedule::command('telegram:meals lunch')->dailyAt(config('hall.telegram.lunch_at'));
Schedule::command('telegram:meals dinner')->dailyAt(config('hall.telegram.dinner_at'));
