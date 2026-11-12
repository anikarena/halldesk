<?php

namespace Tests\Feature;

use App\Notifications\HallAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    public function test_opening_the_list_clears_the_badge_and_new_ones_count_again(): void
    {
        $student = $this->student();
        $student->notify(new HallAlert('Seat allocated', 'Room 101, seat A'));
        $student->notify(new HallAlert('Bill ready', 'October bill'));

        // The two new ones stand out on this visit, then they are read
        $this->actingAs($student)->get(route('notifications.index'))->assertOk()->assertSee('title="Unread"', false);
        $this->assertSame(0, $student->unreadNotifications()->count());

        $this->actingAs($student)->get(route('notifications.index'))->assertDontSee('title="Unread"', false);

        $student->notify(new HallAlert('Complaint resolved', 'C-0001'));
        $this->assertSame(1, $student->unreadNotifications()->count());
    }
}
