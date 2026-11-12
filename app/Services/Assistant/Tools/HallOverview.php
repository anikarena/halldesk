<?php

namespace App\Services\Assistant\Tools;

use App\Enums\ApplicationStatus;
use App\Enums\ComplaintPriority;
use App\Enums\PaymentStatus;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\SeatApplication;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Assistant\Tool;
use App\Services\MealService;
use App\Services\ReportService;

class HallOverview extends Tool
{
    public function __construct(private ReportService $reports, private MealService $meals) {}

    public function name(): string
    {
        return 'hall_overview';
    }

    public function description(): string
    {
        return 'Today\'s summary of the hall for the office: seats and occupancy, seat applications, payments to verify, '
            .'open complaints, money outstanding, meals today and tomorrow, visitors inside.';
    }

    public function allows(User $user): bool
    {
        return $user->isManager();
    }

    public function run(User $user, array $args): array
    {
        $outstanding = $this->reports->outstanding();
        $pending = Payment::query()->where('status', PaymentStatus::Pending);

        return [
            'seats' => $this->reports->occupancy(),
            'seat_applications_waiting' => SeatApplication::query()->where('status', ApplicationStatus::Pending)->count(),
            'payments_to_verify' => ['count' => (clone $pending)->count(), 'amount' => taka((clone $pending)->sum('amount'))],
            'complaints_open' => Complaint::query()->unresolved()->count(),
            'complaints_high_priority' => Complaint::query()->unresolved()->where('priority', ComplaintPriority::High)->count(),
            'outstanding' => [
                'amount' => taka($outstanding['amount']),
                'students' => $outstanding['students'],
                'overdue_amount' => taka($outstanding['overdue_amount']),
                'overdue_students' => $outstanding['overdue_students'],
            ],
            'collected_this_month' => taka($this->reports->collectedThisMonth()),
            'meals_today' => $this->meals->headCount(today()),
            'meals_tomorrow' => $this->meals->headCount(today()->addDay()),
            'visitors_inside' => Visitor::query()->inside()->count(),
            '_links' => [['label' => 'Dashboard', 'url' => route('dashboard')]],
        ];
    }
}
