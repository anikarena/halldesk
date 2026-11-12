<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\HallAlert;
use App\Services\Push\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

/**
 * Push alerts with the push service faked: nothing leaves the computer.
 */
class PushAlertTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    private const PHONE = 'https://fcm.googleapis.com/fcm/send/phone-of-the-student';

    private const LAPTOP = 'https://updates.push.services.mozilla.com/wpush/v2/laptop';

    /** @var list<array{endpoints: list<string>, alert: array<string, mixed>}> */
    public array $sent = [];

    /** @var list<string> endpoints the fake push service reports as gone */
    public array $gone = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['hall.push.public_key' => 'test-public-key', 'hall.push.private_key' => 'test-private-key']);

        $test = $this;
        $this->app->instance(WebPush::class, new class($test) extends WebPush
        {
            public function __construct(private PushAlertTest $test) {}

            protected function deliver(Collection $devices, string $payload): array
            {
                $this->test->sent[] = ['endpoints' => $devices->pluck('endpoint')->all(), 'alert' => json_decode($payload, true)];

                return array_values(array_intersect($this->test->gone, $devices->pluck('endpoint')->all()));
            }
        });
    }

    public function test_a_device_turns_alerts_on_and_off(): void
    {
        $student = $this->student();

        $this->actingAs($student)->postJson(route('push.subscribe'), $this->device(self::PHONE))->assertOk();
        // The browser sends the same subscription again on later visits
        $this->actingAs($student)->postJson(route('push.subscribe'), $this->device(self::PHONE))->assertOk();
        $this->assertSame(1, $student->pushSubscriptions()->count());

        $this->actingAs($student)->deleteJson(route('push.unsubscribe'), ['endpoint' => self::PHONE])->assertOk();
        $this->assertSame(0, $student->pushSubscriptions()->count());
    }

    public function test_a_notification_is_pushed_to_every_device_of_that_user_only(): void
    {
        $student = $this->student();
        $other = $this->student();
        $this->subscribe($student, self::PHONE);
        $this->subscribe($student, self::LAPTOP);
        $this->subscribe($other, 'https://fcm.googleapis.com/fcm/send/someone-else');

        $student->notify(new HallAlert('New bill: 2,500', 'October 2026. Please pay by 10 Nov 2026.', '/bills/7', 'warning'));

        $this->assertCount(1, $this->sent);
        $this->assertEqualsCanonicalizing([self::PHONE, self::LAPTOP], $this->sent[0]['endpoints']);

        // Tapping the alert goes through "open", which marks it read and follows the link
        $notification = $student->notifications()->first();
        $alert = $this->sent[0]['alert'];
        $this->assertSame('New bill: 2,500', $alert['title']);
        $this->assertSame('October 2026. Please pay by 10 Nov 2026.', $alert['body']);
        $this->assertSame('/notifications/'.$notification->id, $alert['url']);
        $this->actingAs($student)->get($alert['url'])->assertRedirect('/bills/7');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_device_follows_the_account_signed_in_on_it(): void
    {
        $first = $this->student();
        $second = $this->student();

        $this->actingAs($first)->postJson(route('push.subscribe'), $this->device(self::PHONE))->assertOk();
        $this->actingAs($second)->postJson(route('push.subscribe'), $this->device(self::PHONE))->assertOk();

        $this->assertSame(0, $first->pushSubscriptions()->count());
        $this->assertSame(1, $second->pushSubscriptions()->count());

        // Nobody can take another account's device off
        $this->actingAs($first)->deleteJson(route('push.unsubscribe'), ['endpoint' => self::PHONE])->assertOk();
        $this->assertSame(1, $second->pushSubscriptions()->count());

        $first->notify(new HallAlert('Payment received', 'Thank you.'));
        $this->assertSame([], $this->sent);
    }

    public function test_a_device_that_is_gone_is_forgotten(): void
    {
        $student = $this->student();
        $this->subscribe($student, self::PHONE);
        $this->subscribe($student, self::LAPTOP);
        $this->gone = [self::PHONE];

        $student->notify(new HallAlert('Complaint resolved', 'C-0001'));

        $this->assertSame([self::LAPTOP], $student->pushSubscriptions()->pluck('endpoint')->all());
    }

    public function test_without_keys_push_is_switched_off(): void
    {
        config(['hall.push.public_key' => null, 'hall.push.private_key' => null]);
        $student = $this->student();
        $this->subscribe($student, self::PHONE);

        $this->actingAs($student)->postJson(route('push.subscribe'), $this->device(self::LAPTOP))->assertStatus(503);
        $this->actingAs($student)->get(route('notifications.index'))->assertOk()->assertDontSee('name="push-key"', false);

        // The bell list still works
        $student->notify(new HallAlert('Seat allocated', 'Room 101, seat A'));
        $this->assertSame([], $this->sent);
        $this->assertSame(1, $student->unreadNotifications()->count());
    }

    public function test_the_page_offers_alerts_when_push_is_set_up(): void
    {
        $this->actingAs($this->student())->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('<meta name="push-key" content="test-public-key">', false)
            ->assertSee('Get alerts on this device');
    }

    public function test_a_subscription_needs_a_real_endpoint_and_keys(): void
    {
        $student = $this->student();

        $this->actingAs($student)->postJson(route('push.subscribe'), ['endpoint' => 'not a link', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])
            ->assertJsonValidationErrors('endpoint');
        $this->actingAs($student)->postJson(route('push.subscribe'), ['endpoint' => self::PHONE])
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
        $this->post(route('logout'));
        $this->postJson(route('push.subscribe'), $this->device(self::PHONE))->assertUnauthorized();
    }

    /**
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}
     */
    private function device(string $endpoint): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'device-public-key', 'auth' => 'device-secret']];
    }

    private function subscribe(User $user, string $endpoint): void
    {
        $user->pushSubscriptions()->create([
            'endpoint_hash' => PushSubscription::hash($endpoint),
            'endpoint' => $endpoint,
            'p256dh' => 'device-public-key',
            'auth' => 'device-secret',
        ]);
    }
}
