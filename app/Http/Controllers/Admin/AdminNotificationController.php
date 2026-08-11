<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PlatformPayment;
use App\Models\SubscriptionChangeRequest;
use App\Models\BusinessFooterChangeRequest;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request, ?string $category = null)
    {
        // Tenant operational activity belongs in the relevant business inbox.
        // Older records are retained, but are never surfaced in the Super
        // Admin notification centre.
        $allNotifications = $request->user()->notifications()
            ->where('data', 'not like', '%business_activity%');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,read,unread'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $legacyCategory = match ($category) {
            'registrations' => 'company_registration',
            'alerts' => 'system_alert',
            default => null,
        };
        $status = $category === 'unread' ? 'unread' : ($filters['status'] ?? null);

        $notifications = (clone $allNotifications)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('data', 'like', '%'.$search.'%'))
            ->when($legacyCategory ?: ($filters['category'] ?? null), fn ($query, $notificationCategory) => $query->whereJsonContains('data->category', $notificationCategory))
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('super-admin.notification-center', [
            'notifications' => $notifications,
            'category' => $category,
            'filters' => $filters,
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

    public function show(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        if (! $item->read_at) {
            $item->markAsRead();
        }

        $data = $item->data ?? [];
        $category = data_get($data, 'category', 'general');
        $payment = $this->paymentFor($data);
        $subscriptionRequest = $payment ? null : $this->subscriptionRequestFor($data);
        $action = match ($category) {
            'company_registration' => ['label' => 'Review Company Registration', 'url' => route('admin.notifications.review', $item->id)],
            'business_detail_change_request' => ['label' => 'Review Request', 'url' => route('admin.notifications.review', $item->id)],
            'footer_change_request' => ['label' => 'Review Footer Change', 'url' => route('admin.notifications.review', $item->id)],
            'subscription' => $subscriptionRequest
                ? ['label' => 'Review Subscription Request', 'url' => route('admin.notifications.review', $item->id)]
                : ($payment
                    ? ['label' => 'Review Payment', 'url' => route('admin.notifications.review', $item->id)]
                    : null),
            default => null,
        };

        return response()->json([
            'id' => (string) $item->id,
            'category' => str($category)->headline()->toString(),
            'title' => data_get($data, 'title', app(\App\Services\PlatformSettingsService::class)->name().' Notification'),
            'message' => data_get($data, 'message'),
            'status' => $item->read_at ? 'Read' : 'Unread',
            'date' => $item->created_at?->format('d M, Y'),
            'time' => $item->created_at?->format('h:i A'),
            'business' => data_get($data, 'business_name') ?? data_get($data, 'company_name'),
            'action' => $action,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
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
        $category = data_get($item->data, 'category');

        if ($category === 'business_detail_change_request') {
            if (!$item->read_at) {
                $item->markAsRead();
            }

            return redirect()->route('admin.business-requests.index', [
                'source' => 'business_detail',
                'request_id' => data_get($item->data, 'change_request_id'),
            ]);
        }

        if ($category === 'footer_change_request') {
            $changeRequest = BusinessFooterChangeRequest::whereKey(data_get($item->data, 'footer_change_request_id'))
                ->where('business_id', data_get($item->data, 'business_id'))
                ->firstOrFail();
            if (! $item->read_at) {
                $item->markAsRead();
            }

            return redirect()->route('admin.business-requests.index', [
                'source' => 'footer',
                'request_id' => $changeRequest->id,
            ]);
        }

        if ($category === 'subscription') {
            $data = $item->data ?? [];
            $payment = $this->paymentFor($data);
            $changeRequest = $payment ? null : $this->subscriptionRequestFor($data);
            if (! $item->read_at) {
                $item->markAsRead();
            }

            if ($changeRequest) {
                return redirect()->route('admin.business-requests.index')
                    ->with('warning', 'Subscription-change requests are no longer available.');
            }

            if ($payment) {
                return redirect()->route('admin.payments', ['payment_id' => $payment->id]);
            }

            return redirect()->route('admin.notifications.index')
                ->with('warning', 'The related subscription record is no longer available.');
        }

        abort_unless($category === 'company_registration', 404);

        $business = Business::query()
            ->findOrFail(data_get($item->data, 'business_id'));

        if (!$item->read_at) {
            $item->markAsRead();
        }

        return redirect()->route('admin.companies.show', $business);
    }

    private function subscriptionRequestFor(array $data): ?SubscriptionChangeRequest
    {
        $relatedType = data_get($data, 'related_type');
        if ($relatedType && $relatedType !== SubscriptionChangeRequest::class) {
            return null;
        }

        $requestId = data_get($data, 'related_id') ?: data_get($data, 'subscription_request_id');
        if (! $requestId) {
            return null;
        }

        $changeRequest = SubscriptionChangeRequest::query()->find($requestId);
        if (! $changeRequest) {
            return null;
        }

        $businessId = data_get($data, 'business_id');

        return $businessId === null || (int) $changeRequest->business_id === (int) $businessId
            ? $changeRequest
            : null;
    }

    private function paymentFor(array $data): ?PlatformPayment
    {
        $isLegacyPayment = str_contains(strtolower((string) data_get($data, 'title')), 'payment')
            || str_contains(strtolower((string) data_get($data, 'message')), 'subscription payment');
        if (data_get($data, 'related_type') !== PlatformPayment::class && ! $isLegacyPayment) {
            return null;
        }

        $paymentId = data_get($data, 'payment_id') ?: data_get($data, 'related_id');
        $payment = $paymentId ? PlatformPayment::query()->find($paymentId) : null;

        return $payment && ((int) $payment->business_id === (int) data_get($data, 'business_id'))
            ? $payment
            : null;
    }
}
