<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Mark a notification read, then jump to its signal (or dashboard). */
    public function go(Request $request, string $notification): RedirectResponse
    {
        $note = $request->user()->notifications()->findOrFail($notification);
        $note->markAsRead();

        $signalId = $note->data['signal_id'] ?? null;

        return $signalId
            ? redirect()->route('signals.show', $signalId)
            : redirect()->route('dashboard');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
