<?php

namespace App\Http\Controllers\Manage;

use App\Enums\NoticeCategory;
use App\Enums\NoticePriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\NoticeRequest;
use App\Models\Notice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(): View
    {
        return view('manage.notices.index', [
            'notices' => Notice::query()->with('creator')->latest('published_at')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Notice([
            'category' => NoticeCategory::General,
            'priority' => NoticePriority::Normal,
        ]));
    }

    public function store(NoticeRequest $request): RedirectResponse
    {
        $notice = Notice::query()->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return redirect()->route('notices.show', $notice)->with('success', 'The notice was published.');
    }

    public function edit(Notice $notice): View
    {
        return $this->form($notice);
    }

    public function update(NoticeRequest $request, Notice $notice): RedirectResponse
    {
        $notice->update($request->validated());

        return redirect()->route('notices.show', $notice)->with('success', 'The notice was updated.');
    }

    public function destroy(Request $request, Notice $notice): RedirectResponse
    {
        $notice->delete();

        return redirect()->route('manage.notices.index')->with('success', 'The notice was deleted.');
    }

    private function form(Notice $notice): View
    {
        return view('manage.notices.form', [
            'notice' => $notice,
            'categories' => NoticeCategory::options(),
            'priorities' => NoticePriority::options(),
        ]);
    }
}
