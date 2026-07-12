<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request, ?string $category = null)
    {
        $notifications = $request->user()->notifications()
            ->when($category === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($category === 'registrations', fn ($query) => $query->where('data', 'like', '%company_registration%'))
            ->when($category === 'alerts', fn ($query) => $query->where('data', 'like', '%system_alert%'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('super-admin.notification-center', ['notifications' => $notifications, 'category' => $category]);
    }

    public function markRead(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
