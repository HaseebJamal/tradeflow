<?php

namespace App\Notifications;

use App\Models\SubscriptionChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly ?int $businessId = null,
        private readonly ?int $subscriptionRequestId = null,
        private readonly array $metadata = [],
    ) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'category' => 'subscription',
            'title' => $this->title,
            'message' => $this->message,
            'business_id' => $this->businessId,
            // Keep the legacy key for existing consumers while recording an
            // authoritative polymorphic reference for every new request.
            'subscription_request_id' => $this->subscriptionRequestId,
            'related_type' => $this->subscriptionRequestId ? SubscriptionChangeRequest::class : null,
            'related_id' => $this->subscriptionRequestId,
        ], $this->metadata);
    }
}
