<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request, ?string $category = null)
    {
        $allNotifications = $request->user()->notifications();
        $notifications = (clone $allNotifications)
            ->when($category === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($category === 'registrations', fn ($query) => $query->where('data', 'like', '%company_registration%'))
            ->when($category === 'alerts', fn ($query) => $query->where('data', 'like', '%system_alert%'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('super-admin.notification-center', [
            'notifications' => $notifications,
            'category' => $category,
            'counts' => [
                'all' => (clone $allNotifications)->count(),
                'unread' => (clone $allNotifications)->whereNull('read_at')->count(),
                'registrations' => (clone $allNotifications)->where('data', 'like', '%company_registration%')->count(),
                'alerts' => (clone $allNotifications)->where('data', 'like', '%system_alert%')->count(),
            ],
        ]);
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

    public function markUnread(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->update(['read_at' => null]);

        return back()->with('success', 'Notification marked as unread.');
    }

    public function destroy(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->delete();

        return back()->with('success', 'Notification dismissed.');
    }

    public function review(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        abort_unless(data_get($item->data, 'category') === 'company_registration', 404);

        $business = Business::with(['owner', 'documents', 'approvalLogs.changedBy'])
            ->findOrFail(data_get($item->data, 'business_id'));

        if (!$item->read_at) {
            $item->markAsRead();
        }

        return view('super-admin.notifications.review-registration', compact('item', 'business'));
    }
}
