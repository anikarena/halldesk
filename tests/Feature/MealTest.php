<?php

namespace Tests\Feature;

use App\Enums\Meal;
use App\Exceptions\HallException;
use App\Models\MealOff;
use App\Services\AllocationService;
use App\Services\MealService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

class MealTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    private MealService $meals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->meals = app(MealService::class);
        // Thursday 5 November 2026, 3 PM
        $this->travelTo(CarbonImmutable::parse('2026-11-05 15:00'));
    }

    public function test_tomorrow_can_be_changed_until_the_cutoff_the_night_before(): void
    {
        $tomorrow = CarbonImmutable::parse('2026-11-06');

        $this->assertTrue($this->meals->isChangeable($tomorrow));
        $this->travelTo(CarbonImmutable::parse('2026-11-05 22:00'));
        $this->assertFalse($this->meals->isChangeable($tomorrow));
        $this->assertFalse($this->meals->isChangeable(CarbonImmutable::parse('2026-11-05')));
    }

    public function test_a_resident_can_turn_a_meal_off_and_on(): void
    {
        $student = $this->resident();
        $day = CarbonImmutable::parse('2026-11-08');

        $this->meals->set($student, $day, Meal::Lunch, false);
        $this->assertDatabaseHas('meal_offs', ['user_id' => $student->id, 'meal' => 'lunch']);

        $this->meals->set($student, $day, Meal::Lunch, true);
        $this->assertSame(0, MealOff::query()->count());
    }

    public function test_turning_a_meal_off_twice_is_harmless(): void
    {
        // Regression: a double click used to fail on the unique index
        $student = $this->resident();
        $day = CarbonImmutable::parse('2026-11-07');

        $this->meals->set($student, $day, Meal::Dinner, false);
        $this->meals->set($student, $day, Meal::Dinner, false);
        $this->meals->setRange($student, $day, $day->addDay(), [Meal::Lunch, Meal::Dinner], false);

        $this->assertSame(4, MealOff::query()->count());
        // Stored as a plain date, the same format the rest of the app compares with
        $this->assertSame('2026-11-07', DB::table('meal_offs')->orderBy('id')->value('date'));
    }

    public function test_locked_days_cannot_be_changed(): void
    {
        $student = $this->resident();

        $this->expectException(HallException::class);
        $this->meals->set($student, CarbonImmutable::parse('2026-11-05'), Meal::Dinner, false);
    }

    public function test_students_without_a_seat_cannot_book_meals(): void
    {
        $this->expectException(HallException::class);
        $this->meals->set($this->student(), CarbonImmutable::parse('2026-11-08'), Meal::Dinner, false);
    }

    public function test_range_off_skips_locked_days(): void
    {
        $student = $this->resident();

        // 5th (today) is locked, 6th to 8th can still change
        $changed = $this->meals->setRange($student, CarbonImmutable::parse('2026-11-05'), CarbonImmutable::parse('2026-11-08'), [Meal::Lunch, Meal::Dinner], false);

        $this->assertSame(3, $changed);
        $this->assertSame(6, MealOff::query()->count());
    }

    public function test_head_count_subtracts_off_meals(): void
    {
        $a = $this->resident('101');
        $this->resident('102');
        $day = CarbonImmutable::parse('2026-11-07');
        $this->meals->set($a, $day, Meal::Dinner, false);

        $this->assertSame(['residents' => 2, 'lunch' => 2, 'dinner' => 1], $this->meals->headCount($day));
    }

    public function test_the_meal_switch_works_from_the_page(): void
    {
        $student = $this->resident();

        $this->actingAs($student)
            ->post(route('student.meals.toggle'), ['date' => '2026-11-07', 'meal' => 'dinner', 'on' => 0])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('meal_offs', ['user_id' => $student->id, 'meal' => 'dinner']);

        $this->actingAs($student)
            ->post(route('student.meals.toggle'), ['date' => '2026-11-05', 'meal' => 'lunch', 'on' => 0])
            ->assertSessionHas('error');
    }

    private function resident(string $room = '101')
    {
        $student = $this->student();
        app(AllocationService::class)->allocate($student, $this->seat($this->room($room)), $this->admin(), CarbonImmutable::parse('2026-10-01'));

        return $student;
    }
}
