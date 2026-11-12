<?php

namespace Tests\Feature;

use App\Enums\Meal;
use App\Models\MealOff;
use App\Models\Notice;
use App\Models\User;
use App\Services\AllocationService;
use App\Services\Telegram\MealList;
use App\Services\Telegram\StaffBot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

/**
 * The hall office bot with the Telegram API faked.
 */
class TelegramBotTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hall.telegram.token' => 'test-token']);
        // Thursday 5 November 2026, 10 AM
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00'));

        Http::fake(['api.telegram.org/*' => fn (HttpRequest $request) => Http::response(['ok' => true, 'result' => match (Str::afterLast($request->url(), '/')) {
            'getMe' => ['id' => 1, 'is_bot' => true, 'username' => 'HallDeskOfficeBot'],
            'sendMessage' => ['message_id' => 10],
            default => true,
        }])]);
    }

    public function test_the_hall_office_links_telegram_from_the_profile(): void
    {
        $staff = $this->staffMember();

        $location = $this->actingAs($staff)->post(route('profile.telegram'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://t.me/HallDeskOfficeBot?start=', $location);
        $code = Str::after($location, 'start=');

        $this->bot()->handle($this->message('555', '/start '.$code));
        $this->assertSame('555', $staff->fresh()->telegram_chat_id);
        $this->assertStringContainsString('Connected as', $this->sent()->last()['text']);

        // The code works only once
        $this->bot()->handle($this->message('777', '/start '.$code));
        $this->assertSame('555', $staff->fresh()->telegram_chat_id);
        $this->assertStringContainsString('expired', $this->sent()->last()['text']);
    }

    public function test_students_cannot_link_telegram(): void
    {
        $this->actingAs($this->student())->post(route('profile.telegram'))->assertForbidden();
    }

    public function test_unknown_chats_are_turned_away(): void
    {
        $this->bot()->handle($this->message('999', 'hello'));

        $this->assertStringContainsString('This bot is for the hall office', $this->sent()->last()['text']);
    }

    public function test_the_meal_list_counts_and_names_who_is_off(): void
    {
        $offStudent = $this->resident('101');
        $this->resident('102');
        MealOff::query()->create(['user_id' => $offStudent->id, 'date' => '2026-11-05', 'meal' => 'lunch']);

        $text = app(MealList::class)->message(Meal::Lunch, today());

        $this->assertStringContainsString('<b>Lunch, Thu, 5 Nov</b>', $text);
        $this->assertStringContainsString('1 of 2 residents eat. 1 off.', $text);
        $this->assertStringContainsString('101-A  '.e($offStudent->name), $text);
        $this->assertStringContainsString('This list is final.', $text);
    }

    public function test_the_meal_list_goes_only_to_linked_hall_office_accounts(): void
    {
        $this->staffMember()->forceFill(['telegram_chat_id' => '111'])->save();
        $this->admin()->forceFill(['telegram_chat_id' => '222'])->save();
        $this->staffMember();
        $this->student()->forceFill(['telegram_chat_id' => '333'])->save();

        Artisan::call('telegram:meals', ['meal' => 'dinner']);

        $this->assertSame(['111', '222'], $this->sent()->pluck('chat_id')->sort()->values()->all());
        $this->assertStringContainsString('<b>Dinner, Thu, 5 Nov</b>', $this->sent()->first()['text']);
    }

    public function test_the_provost_confirms_before_residents_are_notified(): void
    {
        $resident = $this->resident();
        $before = $resident->notifications()->count();
        $this->admin()->forceFill(['telegram_chat_id' => '222'])->save();

        $this->bot()->handle($this->message('222', "Water will be off tomorrow\nFrom 9 AM to 12 PM."));

        $preview = $this->sent()->last();
        $this->assertStringContainsString('Send this to the 1 resident?', $preview['text']);
        $data = $preview['reply_markup']['inline_keyboard'][0][0]['callback_data'];
        $this->assertStringStartsWith('send:', $data);
        $this->assertSame($before, $resident->notifications()->count());
        $this->assertSame(0, Notice::query()->count());

        $this->bot()->handle($this->button('222', $data));

        $this->assertSame($before + 1, $resident->notifications()->count());
        $this->assertSame('Water will be off tomorrow', Notice::query()->sole()->title);
        $this->assertTrue($this->calls('editMessageText')->contains(fn ($body) => str_contains($body['text'], 'Sent to 1 resident.')));

        // A second tap on the same button sends nothing
        $this->bot()->handle($this->button('222', $data));
        $this->assertSame(1, Notice::query()->count());
        $this->assertSame('This message has expired.', $this->calls('answerCallbackQuery')->last()['text']);
    }

    public function test_cancel_sends_nothing(): void
    {
        $this->resident();
        $this->admin()->forceFill(['telegram_chat_id' => '222'])->save();

        $this->bot()->handle($this->message('222', 'Test message'));
        $cancel = $this->sent()->last()['reply_markup']['inline_keyboard'][0][1]['callback_data'];
        $this->bot()->handle($this->button('222', $cancel));

        $this->assertSame(0, Notice::query()->count());
        $this->assertStringContainsString('Cancelled', $this->calls('editMessageText')->last()['text']);
    }

    public function test_staff_cannot_notify_residents(): void
    {
        $this->resident();
        $this->staffMember()->forceFill(['telegram_chat_id' => '111'])->save();

        $this->bot()->handle($this->message('111', 'Everyone come to the office'));

        $this->assertStringContainsString('Only the provost', $this->sent()->last()['text']);
        $this->assertArrayNotHasKey('reply_markup', $this->sent()->last());
        $this->assertSame(0, Notice::query()->count());
    }

    private function bot(): StaffBot
    {
        return app(StaffBot::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function message(string $chatId, string $text): array
    {
        return ['update_id' => 1, 'message' => ['message_id' => 1, 'chat' => ['id' => (int) $chatId, 'type' => 'private'], 'text' => $text]];
    }

    /**
     * @return array<string, mixed>
     */
    private function button(string $chatId, string $data): array
    {
        return ['update_id' => 2, 'callback_query' => [
            'id' => 'cb'.Str::random(4),
            'data' => $data,
            'message' => ['message_id' => 10, 'chat' => ['id' => (int) $chatId, 'type' => 'private']],
        ]];
    }

    /**
     * Bodies of the requests made to one Bot API method.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function calls(string $method): Collection
    {
        return Http::recorded(fn (HttpRequest $request) => str_ends_with($request->url(), '/'.$method))
            ->map(fn (array $pair) => $pair[0]->data())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sent(): Collection
    {
        return $this->calls('sendMessage');
    }

    private function resident(string $room = '101'): User
    {
        $student = $this->student();
        app(AllocationService::class)->allocate($student, $this->seat($this->room($room)), $this->admin(), CarbonImmutable::parse('2026-10-01'));

        return $student;
    }
}
