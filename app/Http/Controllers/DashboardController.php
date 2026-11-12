<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Enums\PaymentStatus;
use App\Models\Allocation;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\SeatApplication;
use App\Models\User;
use App\Models\Visitor;
use App\Services\MealService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One URL, two dashboards: hall office (admin, staff) and student.
 * Both start with the things that need attention right now.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports, MealService $meals): View
    {
        $user = $request->user();

        return $user->isManager()
            ? $this->manager($user, $reports, $meals)
            : $this->student($user, $meals);
    }

    private function manager(User $user, ReportService $reports, MealService $meals): View
    {
        // Has last month been billed yet? Shown as a reminder to the admin.
        $lastPeriod = today()->toImmutable()->startOfMonth()->subMonthNoOverflow();
        $lastMonthUnbilled = $user->isAdmin()
            && Allocation::query()->overlapping($lastPeriod->toDateString(), $lastPeriod->endOfMonth()->toDateString())->exists()
            && ! Invoice::query()->where('kind', Invoice::KIND_MONTHLY)->where('period', $lastPeriod->format('Y-m'))->exists();

        return view('dashboard.manager', [
            'lastPeriod' => $lastPeriod->format('Y-m'),
            'lastMonthUnbilled' => $lastMonthUnbilled,
            'occupancy' => $reports->occupancy(),
            'floors' => $reports->occupancyByFloor(),
            'finance' => $reports->financeByMonth(6),
            'outstanding' => $reports->outstanding(),
            'collectedThisMonth' => $reports->collectedThisMonth(),
            'mealsToday' => $meals->headCount(today()),
            'mealsTomorrow' => $meals->headCount(today()->addDay()),
            'visitorsInside' => Visitor::query()->inside()->count(),

            'pendingApplications' => $user->isAdmin()
                ? SeatApplication::query()->where('status', ApplicationStatus::Pending)->with('user.profile')->oldest()->limit(5)->get()
                : collect(),
            'pendingApplicationCount' => SeatApplication::query()->where('status', ApplicationStatus::Pending)->count(),

            'pendingPayments' => Payment::query()->where('status', PaymentStatus::Pending)->with('user', 'invoice')->oldest()->limit(5)->get(),
            'pendingPaymentCount' => Payment::query()->where('status', PaymentStatus::Pending)->count(),
            'pendingPaymentSum' => (int) Payment::query()->where('status', PaymentStatus::Pending)->sum('amount'),

            'urgentComplaints' => Complaint::query()->unresolved()
                ->with('user', 'room')
                ->orderByRaw("case priority when 'high' then 3 when 'medium' then 2 else 1 end desc")
                ->oldest()
                ->limit(5)
                ->get(),
            'openComplaintCount' => Complaint::query()->where('status', ComplaintStatus::Open)->count(),
            'highComplaintCount' => Complaint::query()->unresolved()->where('priority', ComplaintPriority::High)->count(),
            'myComplaintCount' => Complaint::query()->unresolved()->where('assigned_to', $user->id)->count(),

            'notices' => Notice::query()->live()->boardOrder()->limit(3)->get(),
        ]);
    }

    private function student(User $user, MealService $meals): View
    {
        $allocation = $user->activeAllocation()->with('seat.room')->first();

        $roommates = $allocation
            ? Allocation::query()->active()
                ->whereHas('seat', fn ($q) => $q->where('room_id', $allocation->seat->room_id))
                ->where('user_id', '!=', $user->id)
                ->with('user.profile', 'seat')
                ->get()
            : collect();

        $openInvoices = Invoice::query()->where('user_id', $user->id)->outstanding()->orderBy('due_date')->get();

        $tomorrow = today()->addDay();
        $mealData = null;
        if ($allocation) {
            $offs = $meals->offMap($user, today(), $tomorrow);
            $mealData = [
                'today' => today(),
                'tomorrow' => $tomorrow,
                'offs' => $offs,
                'tomorrowCutoff' => $meals->cutoffFor($tomorrow),
                'tomorrowChangeable' => $meals->isChangeable($tomorrow),
                'month' => $meals->monthToDate($user),
            ];
        }

        return view('dashboard.student', [
            'user' => $user->load('profile'),
            'allocation' => $allocation,
            'roommates' => $roommates,
            'application' => $allocation ? null : $user->applications()->first(),
            'openInvoices' => $openInvoices,
            'due' => $openInvoices->sum(fn (Invoice $i) => $i->balance()),
            'overdue' => $openInvoices->filter(fn (Invoice $i) => $i->isOverdue()),
            'meal' => $mealData,
            'complaints' => $user->complaints()->unresolved()->latest()->limit(3)->get(),
            'notices' => Notice::query()->live()->boardOrder()->limit(4)->get(),
        ]);
    }
}
