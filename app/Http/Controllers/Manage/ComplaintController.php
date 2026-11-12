<?php

namespace App\Http\Controllers\Manage;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use App\Services\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function __construct(private ComplaintService $complaints) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...ComplaintStatus::values(), 'unresolved', 'all'])],
            'priority' => ['nullable', Rule::enum(ComplaintPriority::class)],
            'category' => ['nullable', Rule::enum(ComplaintCategory::class)],
            'assigned' => ['nullable', Rule::in(['me', 'none'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'unresolved';

        $complaints = Complaint::query()
            ->with('user.profile', 'room', 'assignee')
            ->when($status === 'unresolved', fn ($q) => $q->unresolved())
            ->when(! in_array($status, ['unresolved', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when(($filters['assigned'] ?? null) === 'me', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when(($filters['assigned'] ?? null) === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->search($term))
                ->orWhereHas('room', fn ($r) => $r->where('number', $term))))
            // Most urgent first: high priority, then oldest
            ->orderByRaw("case status when 'resolved' then 1 else 0 end")
            ->orderByRaw("case priority when 'high' then 3 when 'medium' then 2 else 1 end desc")
            ->oldest()
            ->paginate(20)
            ->withQueryString();

        return view('manage.complaints.index', [
            'complaints' => $complaints,
            'filters' => $filters,
            'status' => $status,
            'counts' => Complaint::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Complaint $complaint): View
    {
        return view('manage.complaints.show', [
            'complaint' => $complaint->load('user.profile', 'room', 'assignee', 'updates.user'),
            'staff' => User::query()->managers()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
            'priority' => ['required', Rule::enum(ComplaintPriority::class)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', ['admin', 'staff'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->complaints->manage(
            $complaint,
            $request->user(),
            ComplaintStatus::from($data['status']),
            ComplaintPriority::from($data['priority']),
            $data['assigned_to'] ?? null,
            $data['note'] ?? null,
        );

        return back()->with('success', "Complaint {$complaint->reference()} was updated.");
    }

    public function comment(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $this->complaints->comment($complaint, $request->user(), $data['body']);

        return back()->with('success', 'Your reply was sent to the student.');
    }
}
