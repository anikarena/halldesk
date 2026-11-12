<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportService $reports): View
    {
        return view('manage.reports.index', [
            'occupancy' => $reports->occupancy(),
            'floors' => $reports->occupancyByFloor(),
            'roomTypes' => $reports->occupancyByRoomType(),
            'finance' => $reports->financeByMonth(12),
            'outstanding' => $reports->outstanding(),
            'complaints' => $reports->complaintStats(),
            'topDues' => $this->duesQuery()->limit(10)->get(),
        ]);
    }

    /**
     * Every student with unpaid bills, as a spreadsheet file.
     */
    public function duesCsv(): StreamedResponse
    {
        $rows = $this->duesQuery()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student ID', 'Name', 'Department', 'Phone', 'Seat', 'Open bills', 'Oldest due date', 'Amount due (BDT)']);
            foreach ($rows as $student) {
                fputcsv($out, [
                    $student->profile?->student_id,
                    $student->name,
                    $student->profile?->department,
                    $student->phone,
                    $student->activeAllocation?->seat->code() ?? 'Left the hall',
                    $student->open_bills,
                    $student->oldest_due,
                    $student->due_total,
                ]);
            }
            fclose($out);
        }, 'hall-dues-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function duesQuery()
    {
        $open = Invoice::query()->outstanding()
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as open_bills, min(due_date) as oldest_due, sum(total - paid) as due_total');

        return User::query()
            ->joinSub($open, 'dues', 'dues.user_id', '=', 'users.id')
            ->select('users.*', 'dues.open_bills', 'dues.oldest_due', 'dues.due_total')
            ->with('profile', 'activeAllocation.seat.room')
            ->orderByDesc('dues.due_total');
    }
}
