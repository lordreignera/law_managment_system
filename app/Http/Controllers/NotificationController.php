<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unread(Request $request)
    {
        $notifications = $request->user()->unreadNotifications()->latest()->limit(10)->get();

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => (string) $notification->id,
                    'subject' => $notification->data['subject'] ?? 'Notification',
                    'body' => $notification->data['body'] ?? '',
                    'url' => $notification->data['url'] ?? route('notifications.index'),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function index(Request $request)
    {
        return view('modules.notifications.index', ['notifications' => $request->user()->notifications()->paginate(20)]);
    }

    public function read(Request $request, string $notification)
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back();
    }
}
