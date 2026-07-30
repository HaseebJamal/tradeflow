<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmailChangeRequest;
use App\Models\UserDetailChangeRequest;
use App\Services\CompanyPermissionService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class BusinessNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensureAccess($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,read,unread'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = $user->notifications()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('data', 'like', '%'.$search.'%'))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereJsonContains('data->category', $category))
            ->when(($filters['status'] ?? null) === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when(($filters['status'] ?? null) === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
        if ($user->role === 'super_admin') {
            $query->where('data', 'not like', '%business_activity%');
        }

        $notifications = $query->latest()->paginate(10)->withQueryString();
        $profileRequests = collect();
        $emailChangeRequests = collect();
        $canApproveEmailChanges = $user->business_id
            && ($user->role === 'business_owner'
                || app(CompanyPermissionService::class)->allowsUser($user, 'users.approve_email_change', $user->business));

        if ($user->role === 'business_owner' && $user->business_id) {
            $items = $notifications->getCollection();
            $profileIds = $items->filter(fn ($notification) => data_get($notification->data, 'category') === 'user_detail_change_request'
                    && (int) data_get($notification->data, 'business_id') === (int) $user->business_id)
                ->pluck('data.change_request_id')->filter()->unique()->values();
            $profileRequests = UserDetailChangeRequest::with('user')
                ->where('business_id', $user->business_id)
                ->whereIn('id', $profileIds)
                ->get()
                ->keyBy('id');
        }

        if ($canApproveEmailChanges) {
            $emailRequestIds = $notifications->getCollection()
                ->filter(fn ($notification) => data_get($notification->data, 'category') === 'staff_email_change_request'
                    && (int) data_get($notification->data, 'business_id') === (int) $user->business_id)
                ->pluck('data.email_change_request_id')->filter()->unique()->values();
            $emailChangeRequests = EmailChangeRequest::with('user')
                ->where('business_id', $user->business_id)
                ->whereIn('id', $emailRequestIds)
                ->get()
                ->keyBy('id');
        }

        return view('auth.notifications', compact('notifications', 'profileRequests', 'emailChangeRequests', 'canApproveEmailChanges', 'filters'));
    }

    public function markRead(Request $request, string $notification)
    {
        $item = $this->notificationForUser($request, $notification);
        if (! $item->read_at) {
            $item->markAsRead();
            $this->audit($request, 'notification_marked_read', $item, 'Notification marked as read.');
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markUnread(Request $request, string $notification)
    {
        $item = $this->notificationForUser($request, $notification);
        if ($item->read_at) {
            $item->update(['read_at' => null]);
            $this->audit($request, 'notification_marked_unread', $item, 'Notification marked as unread.');
        }

        return back()->with('success', 'Notification marked as unread.');
    }

    public function destroy(Request $request, string $notification)
    {
        $item = $this->notificationForUser($request, $notification);
        $this->audit($request, 'notification_deleted', $item, 'Notification deleted.');
        $item->delete();

        return back()->with('success', 'Notification deleted.');
    }

    public function markAllRead(Request $request)
    {
        $this->ensureAccess($request);
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($count) {
            AuditLog::create([
                'business_id' => $request->user()->business_id,
                'module' => 'Notifications',
                'action' => 'notifications_marked_all_read',
                'record_type' => 'DatabaseNotification',
                'description' => $count.' notifications marked as read.',
                'new_values' => ['count' => $count],
            ]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    private function ensureAccess(Request $request): void
    {
        $user = $request->user();
        if ($user?->business_id && ! app(CompanyPermissionService::class)->allowsUser($user, 'notifications.view')) {
            abort(403, 'Notification access is not enabled for your account.');
        }
    }

    private function notificationForUser(Request $request, string $notification): DatabaseNotification
    {
        $this->ensureAccess($request);

        return $request->user()->notifications()->findOrFail($notification);
    }

    private function audit(Request $request, string $action, DatabaseNotification $notification, string $description): void
    {
        AuditLog::create([
            'business_id' => $request->user()->business_id,
            'module' => 'Notifications',
            'action' => $action,
            'record_type' => 'DatabaseNotification',
            // Laravel database notifications use UUID primary keys while the
            // audit table's record_id is intentionally an unsigned integer.
            // Keep the UUID as audit metadata instead of coercing/truncating
            // it into that numeric column.
            'record_id' => null,
            'description' => $description,
            'new_values' => [
                'notification_id' => (string) $notification->id,
                'category' => data_get($notification->data, 'category'),
            ],
        ]);
    }
}
