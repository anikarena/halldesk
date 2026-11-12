<?php

namespace App\Http\Controllers;

use App\Enums\NoticeCategory;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The notice board everybody sees. Admins manage notices in Manage\NoticeController.
 */
class NoticeController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['category' => ['nullable', Rule::enum(NoticeCategory::class)]]);

        $notices = Notice::query()->live()
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->boardOrder()
            ->paginate(10)
            ->withQueryString();

        return view('notices.index', [
            'notices' => $notices,
            'category' => $request->query('category'),
        ]);
    }

    public function show(Request $request, Notice $notice): View
    {
        abort_unless($notice->isLive() || $request->user()->isAdmin(), 404);

        return view('notices.show', ['notice' => $notice->load('creator')]);
    }
}
