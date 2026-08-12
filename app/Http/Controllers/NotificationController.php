<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $user = auth()->user();
        $notifications = $user->unreadNotifications()->limit(10)->get();

        return response()->json([
            'count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(
                fn ($notification) => NotificationPresenter::forDropdown($notification)
            )->values(),
        ]);
    }

    public function markAsRead(string $id): RedirectResponse|JsonResponse
    {
        $notification = auth()->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
        }

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllAsRead(): RedirectResponse|JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
        }

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
