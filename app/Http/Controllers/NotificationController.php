<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Show the authenticated user's notification centre.
     */
    public function index(Request $request): View
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Mark a single notification as read — ownership enforced.
     */
    public function markRead(DatabaseNotification $notification): RedirectResponse
    {
        // IDOR protection: ensure the notification belongs to the authenticated user
        if ($notification->notifiable_id !== auth()->id()) {
            abort(403, 'You cannot mark another user\'s notification as read.');
        }

        $notification->markAsRead();

        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all of the authenticated user's notifications as read.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
