<?php

namespace App\Http\Controllers\Student;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintPriority;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
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
        return view('student.complaints.index', [
            'complaints' => $request->user()->complaints()->with('assignee')->latest()->paginate(10),
            'isResident' => $request->user()->isResident(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->isResident()) {
            return redirect()->route('student.complaints.index')->with('error', 'Only residents can submit complaints.');
        }

        return view('student.complaints.create', [
            'categories' => ComplaintCategory::options(),
            'priorities' => ComplaintPriority::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'priority' => ['required', Rule::enum(ComplaintPriority::class)],
        ]);

        $complaint = $this->complaints->create($request->user(), $data);

        return redirect()->route('student.complaints.show', $complaint)
            ->with('success', "Complaint {$complaint->reference()} has been submitted.");
    }

    public function show(Request $request, Complaint $complaint): View
    {
        abort_unless($complaint->user_id === $request->user()->id, 403);

        return view('student.complaints.show', [
            'complaint' => $complaint->load('room', 'assignee', 'updates.user'),
        ]);
    }

    public function comment(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($complaint->user_id === $request->user()->id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $this->complaints->comment($complaint, $request->user(), $data['body']);

        return back()->with('success', 'Your comment has been added.');
    }

    public function reopen(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($complaint->user_id === $request->user()->id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']], ['body.required' => 'Tell us what is still wrong.']);

        $this->complaints->reopen($complaint, $request->user(), $data['body']);

        return back()->with('success', 'The complaint has been reopened.');
    }
}
