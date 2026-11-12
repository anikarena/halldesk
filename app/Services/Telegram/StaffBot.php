<?php

namespace App\Services\Telegram;

use App\Enums\NoticeCategory;
use App\Enums\NoticePriority;
use App\Models\Notice;
use App\Models\User;
use App\Notifications\HallAlert;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The hall office bot, used in a private chat with Telegram.
 *
 *  - The provost and staff link their HallDesk account from the profile page.
 *  - At lunch and dinner time every linked account gets the meal list.
 *  - Only the provost (admin) can notify every resident: whatever they write
 *    is shown back with Send / Cancel, and nothing goes out before Send.
 */
class StaffBot
{
    private const LINK_MINUTES = 15;

    private const DRAFT_MINUTES = 30;

    private const NOT_LINKED = 'This bot is for the hall office. Open your profile in HallDesk and press Connect Telegram.';

    public function __construct(private Telegram $telegram, private MealList $meals) {}

    /**
     * A one time code for the t.me/bot?start=code link on the profile page.
     */
    public function linkCode(User $user): string
    {
        $code = Str::random(32);
        Cache::put('telegram.link.'.$code, $user->id, now()->addMinutes(self::LINK_MINUTES));

        return $code;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->onButton($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        if (! $message || ($message['chat']['type'] ?? '') !== 'private' || ! isset($message['text'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text']);
        $user = $this->userFor($chatId);

        if (str_starts_with($text, '/start')) {
            $this->start($chatId, trim(substr($text, 6)), $user);

            return;
        }
        if (! $user) {
            $this->telegram->send($chatId, self::NOT_LINKED);

            return;
        }

        match (true) {
            $text === '/meals' => $this->telegram->send($chatId, $this->meals->next()),
            $text === '/stop' => $this->unlink($user, $chatId),
            str_starts_with($text, '/') => $this->telegram->send($chatId, $this->help($user)),
            $user->isAdmin() => $this->draft($user, $chatId, $text),
            default => $this->telegram->send($chatId, 'Only the provost can send notifications to residents. '
                .'You get the meal list here at '.$this->times().'. Send /meals to see the next one now.'),
        };
    }

    /**
     * Sends a message to every resident: a notification in HallDesk and a post on
     * the notice board, so it is still there after the notification is read.
     */
    public function notifyResidents(User $provost, string $text): int
    {
        $residents = User::query()->residents()->where('is_active', true)->get();

        $notice = Notice::query()->create([
            'title' => Str::limit(Str::before($text, "\n"), 100),
            'body' => $text,
            'category' => NoticeCategory::General,
            'priority' => NoticePriority::Important,
            'is_pinned' => false,
            'is_public' => false,
            'published_at' => now(),
            'created_by' => $provost->id,
        ]);

        Notification::send($residents, new HallAlert('Message from the provost', Str::limit($text, 200), route('notices.show', $notice)));

        return $residents->count();
    }

    private function start(string $chatId, string $code, ?User $user): void
    {
        if ($code === '') {
            $this->telegram->send($chatId, $user ? $this->help($user) : self::NOT_LINKED);

            return;
        }

        $userId = Cache::pull('telegram.link.'.$code);
        $target = $userId ? User::query()->find($userId) : null;
        if (! $target || ! $target->isManager() || ! $target->is_active) {
            $this->telegram->send($chatId, 'This link has expired. Open your profile in HallDesk and press Connect Telegram again.');

            return;
        }

        // One Telegram account belongs to one HallDesk account
        DB::transaction(function () use ($chatId, $target) {
            User::query()->where('telegram_chat_id', $chatId)->whereKeyNot($target->id)->update(['telegram_chat_id' => null]);
            $target->forceFill(['telegram_chat_id' => $chatId])->save();
        });

        $this->telegram->send($chatId, 'Connected as <b>'.e($target->name).'</b> ('.e($target->role->label()).").\n\n".$this->help($target));
    }

    private function unlink(User $user, string $chatId): void
    {
        $user->forceFill(['telegram_chat_id' => null])->save();
        $this->telegram->send($chatId, 'Disconnected. You will not get the meal lists any more. Connect again from your HallDesk profile.');
    }

    private function draft(User $provost, string $chatId, string $text): void
    {
        if (mb_strlen($text) > 1000) {
            $this->telegram->send($chatId, 'Please keep the message under 1000 characters.');

            return;
        }

        $residents = User::query()->residents()->where('is_active', true)->count();
        if ($residents === 0) {
            $this->telegram->send($chatId, 'There are no residents to notify.');

            return;
        }

        $id = Str::random(12);
        Cache::put('telegram.draft.'.$id, ['user_id' => $provost->id, 'text' => $text], now()->addMinutes(self::DRAFT_MINUTES));

        $this->telegram->send(
            $chatId,
            '<b>Send this to '.($residents === 1 ? 'the 1 resident' : "all {$residents} residents")."?</b>\n\n".e($text)
                ."\n\nThey get it as a notification in HallDesk, and it is posted on the notice board.",
            [[['text' => 'Send', 'callback_data' => 'send:'.$id], ['text' => 'Cancel', 'callback_data' => 'cancel:'.$id]]],
        );
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function onButton(array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $messageId = (int) ($callback['message']['message_id'] ?? 0);
        [$action, $id] = array_pad(explode(':', (string) ($callback['data'] ?? ''), 2), 2, '');

        $user = $this->userFor($chatId);
        $draft = Cache::get('telegram.draft.'.$id);

        if (! $user?->isAdmin() || ! $draft || $draft['user_id'] !== $user->id || ! Cache::pull('telegram.draft.'.$id)) {
            // Expired, already used (a double tap), or not the provost
            $this->telegram->answerCallback($callback['id'], 'This message has expired.');

            return;
        }

        if ($action === 'send') {
            $count = $this->notifyResidents($user, $draft['text']);
            $this->telegram->edit($chatId, $messageId, "<b>Sent to {$count} ".Str::plural('resident', $count).".</b>\n\n".e($draft['text']));
            $this->telegram->answerCallback($callback['id'], 'Sent');
        } else {
            $this->telegram->edit($chatId, $messageId, 'Cancelled. Nothing was sent.');
            $this->telegram->answerCallback($callback['id']);
        }
    }

    private function userFor(string $chatId): ?User
    {
        $user = User::query()->where('telegram_chat_id', $chatId)->where('is_active', true)->first();

        return $user?->isManager() ? $user : null;
    }

    private function help(User $user): string
    {
        $text = 'You get the lunch and dinner lists here at '.$this->times().".\n/meals shows the next one now.\n/stop disconnects this chat.";

        return $user->isAdmin()
            ? $text."\n\nAs provost you can also write any message here to notify every resident. You will see it first with a Send button."
            : $text;
    }

    private function times(): string
    {
        $format = fn (string $time) => CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A');

        return $format(config('hall.telegram.lunch_at')).' and '.$format(config('hall.telegram.dinner_at'));
    }
}
