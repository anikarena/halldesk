<?php

namespace Tests\Feature;

use App\Exceptions\HallException;
use App\Models\Invoice;
use App\Models\MealOff;
use App\Services\AllocationService;
use App\Services\BillingService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00'));
        $this->billing = app(BillingService::class);
        // Default rates: lunch 70, dinner 70, electricity 100 a month
    }

    public function test_full_month_bill_is_rent_electricity_and_every_meal(): void
    {
        $student = $this->student();
        app(AllocationService::class)->allocate($student, $this->seat($this->room(rent: 1000)), $this->admin(), CarbonImmutable::parse('2026-09-01'));
        MealOff::query()->create(['user_id' => $student->id, 'date' => '2026-10-10', 'meal' => 'lunch']);

        $this->billing->generate('2026-10', $this->admin());

        $invoice = Invoice::query()->where('user_id', $student->id)->firstOrFail();
        // 1000 rent + 100 electricity + 30 lunches x 70 + 31 dinners x 70
        $this->assertSame(1000 + 100 + 30 * 70 + 31 * 70, $invoice->total);
        $this->assertSame('2026-11-10', $invoice->due_date->toDateString());
        $this->assertCount(4, $invoice->items);
        $this->assertSame(['Electricity (full month)', 100], [$invoice->items[1]->description, $invoice->items[1]->amount]);
    }

    public function test_no_electricity_line_when_the_charge_is_zero(): void
    {
        app(Settings::class)->put(['electricity_charge' => 0]);
        app(AllocationService::class)->allocate($this->student(), $this->seat($this->room()), $this->admin(), CarbonImmutable::parse('2026-09-01'));

        $lines = collect($this->billing->preview('2026-10')->first()['lines']);

        $this->assertFalse($lines->contains(fn ($l) => str_starts_with($l['description'], 'Electricity')));
    }

    public function test_a_meal_off_on_the_last_day_of_the_month_is_not_charged(): void
    {
        // Regression: dates stored as "Y-m-d 00:00:00" fell outside whereBetween(.., '2026-10-31')
        $student = $this->student();
        app(AllocationService::class)->allocate($student, $this->seat($this->room()), $this->admin(), CarbonImmutable::parse('2026-09-01'));
        MealOff::query()->create(['user_id' => $student->id, 'date' => CarbonImmutable::parse('2026-10-31'), 'meal' => 'dinner']);

        $lines = collect($this->billing->preview('2026-10')->first()['lines']);

        $this->assertSame(30, $lines->first(fn ($l) => str_starts_with($l['description'], 'Dinner'))['quantity']);
    }

    public function test_rent_and_electricity_are_prorated_by_days_stayed(): void
    {
        $student = $this->student();
        // Moved in on 22 October: 10 of 31 days
        app(AllocationService::class)->allocate($student, $this->seat($this->room(rent: 3100)), $this->admin(), CarbonImmutable::parse('2026-10-22'));

        $lines = collect($this->billing->preview('2026-10')->first()['lines']);

        $this->assertSame(1000, $lines[0]['amount']);
        // 100 x 10 / 31 = 32.26
        $this->assertSame(32, $lines->firstWhere('description', 'Electricity (10 of 31 days)')['amount']);
        $this->assertSame(10, $lines->firstWhere('description', 'Lunch, 10 meals')['quantity']);
    }

    public function test_a_transfer_gives_one_rent_line_per_room(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $service = app(AllocationService::class);
        $old = $service->allocate($student, $this->seat($this->room('101', rent: 3100)), $admin, CarbonImmutable::parse('2026-09-01'));
        $service->transfer($old, $this->seat($this->room('201', rent: 6200)), $admin, CarbonImmutable::parse('2026-10-16'));

        $lines = collect($this->billing->preview('2026-10')->first()['lines']);
        $rent = $lines->filter(fn ($l) => str_starts_with($l['description'], 'Seat rent'));

        // 15 days x 100 + 16 days x 200
        $this->assertSame([1500, 3200], $rent->pluck('amount')->all());
        $this->assertSame(31, $lines->firstWhere('description', 'Lunch, 31 meals')['quantity']);
        // Electricity once for the whole month, not once per room
        $this->assertSame(100, $lines->firstWhere('description', 'Electricity (full month)')['amount']);
    }

    public function test_generating_twice_does_not_bill_anyone_twice(): void
    {
        app(AllocationService::class)->allocate($this->student(), $this->seat($this->room()), $this->admin(), CarbonImmutable::parse('2026-09-01'));

        $first = $this->billing->generate('2026-10', $this->admin());
        $second = $this->billing->generate('2026-10', $this->admin());

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_the_current_month_cannot_be_billed_yet(): void
    {
        $this->expectException(HallException::class);
        $this->billing->generate('2026-11', $this->admin());
    }
}
