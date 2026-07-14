<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()->notifications()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function unread(): JsonResponse
    {
        $notifications = auth()->user()->unreadNotifications()->limit(10)->get();

        return response()->json([
            'count' => auth()->user()->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(string $id): RedirectResponse|JsonResponse
    {
        $notification = auth()->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Notification marked as read.']);
        }

        return back();
    }

    public function markAllAsRead(): RedirectResponse|JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'All notifications marked as read.']);
        }

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
