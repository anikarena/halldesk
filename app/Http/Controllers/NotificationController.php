<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Opening the list counts as reading it: the bell badge clears and starts
     * again from 1 with the next notification. The page is loaded first, so the
     * ones that were new still stand out on this visit.
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(15);
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications.index', ['notifications' => $notifications]);
    }

    /**
     * Mark one notification as read and follow its link.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
