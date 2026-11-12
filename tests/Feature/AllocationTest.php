<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\RoomStatus;
use App\Exceptions\HallException;
use App\Models\Allocation;
use App\Models\SeatApplication;
use App\Services\AllocationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

class AllocationTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    private AllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AllocationService::class);
    }

    public function test_a_vacant_seat_can_be_allocated(): void
    {
        $room = $this->room();
        $student = $this->student();

        $allocation = $this->service->allocate($student, $this->seat($room), $this->admin());

        $this->assertTrue($allocation->isActive());
        $this->assertTrue($student->fresh()->isResident());
        $this->assertSame(1, $student->notifications()->count());
    }

    public function test_a_taken_seat_cannot_be_given_to_a_second_student(): void
    {
        $room = $this->room();
        $admin = $this->admin();
        $this->service->allocate($this->student(), $this->seat($room), $admin);

        $this->expectException(HallException::class);
        $this->service->allocate($this->student(), $this->seat($room), $admin);
    }

    public function test_a_student_cannot_hold_two_seats(): void
    {
        $room = $this->room();
        $admin = $this->admin();
        $student = $this->student();
        $this->service->allocate($student, $this->seat($room, 'A'), $admin);

        $this->expectException(HallException::class);
        $this->service->allocate($student, $this->seat($room, 'B'), $admin);
    }

    public function test_the_database_itself_blocks_a_double_allocation(): void
    {
        // Simulates two admins that both passed the checks at the same moment
        $room = $this->room();
        $seat = $this->seat($room);
        $this->service->allocate($this->student(), $seat, $this->admin());

        $this->expectException(UniqueConstraintViolationException::class);
        Allocation::query()->create([
            'user_id' => $this->student()->id, 'seat_id' => $seat->id, 'start_date' => today(),
            'status' => 'active', 'active_seat_id' => $seat->id, 'active_user_id' => 999,
        ]);
    }

    public function test_rooms_under_maintenance_cannot_receive_students(): void
    {
        $room = $this->room(status: RoomStatus::Maintenance);

        $this->expectException(HallException::class);
        $this->service->allocate($this->student(), $this->seat($room), $this->admin());
    }

    public function test_allocating_approves_the_pending_application(): void
    {
        $student = $this->student();
        $application = SeatApplication::query()->create(['user_id' => $student->id, 'reason' => str_repeat('Far from home. ', 3), 'status' => 'pending']);

        $allocation = $this->service->allocate($student, $this->seat($this->room()), $this->admin());

        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);
        $this->assertSame($application->id, $allocation->application_id);
    }

    public function test_transfer_ends_the_old_stay_the_day_before(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $old = $this->service->allocate($student, $this->seat($this->room('101')), $admin, CarbonImmutable::parse('2026-09-01'));
        $newSeat = $this->seat($this->room('201'));

        $new = $this->service->transfer($old, $newSeat, $admin, CarbonImmutable::parse('2026-09-15'));

        $old->refresh();
        $this->assertFalse($old->isActive());
        $this->assertSame('2026-09-14', $old->end_date->toDateString());
        $this->assertNull($old->active_seat_id);
        $this->assertSame('2026-09-15', $new->start_date->toDateString());
        $this->assertSame($newSeat->id, $new->seat_id);
    }

    public function test_vacating_frees_the_seat_for_someone_else(): void
    {
        $admin = $this->admin();
        $seat = $this->seat($this->room());
        $first = $this->service->allocate($this->student(), $seat, $admin, today()->subDays(10));

        $this->service->vacate($first, $admin, today(), 'Graduated');
        $second = $this->service->allocate($this->student(), $seat, $admin);

        $this->assertTrue($second->isActive());
        $this->assertSame('Graduated', $first->fresh()->end_reason);
    }

    public function test_admin_can_approve_an_application_through_the_page(): void
    {
        $student = $this->student();
        $application = SeatApplication::query()->create(['user_id' => $student->id, 'reason' => str_repeat('Need a seat. ', 4), 'status' => 'pending']);
        $seat = $this->seat($this->room());

        $this->actingAs($this->admin())
            ->post(route('manage.applications.approve', $application), ['seat_id' => $seat->id, 'start_date' => today()->toDateString()])
            ->assertRedirect(route('manage.applications.index'))
            ->assertSessionHas('success');

        $this->assertTrue($student->fresh()->isResident());
    }
}
