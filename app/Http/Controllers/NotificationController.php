<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $userId = auth()->user()->userid;

        $items = AppNotification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'type', 'title', 'message', 'read_at', 'created_at']);

        return response()->json([
            'notifications' => $items,
            'unread_count' => AppNotification::where('user_id', $userId)->whereNull('read_at')->count(),
        ], 200);
    }

    public function markAllRead()
    {
        AppNotification::where('user_id', auth()->user()->userid)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read'], 200);
    }
}
