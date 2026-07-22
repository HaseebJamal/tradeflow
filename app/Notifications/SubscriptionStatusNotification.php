<?php

namespace App\Notifications;

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
    ) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return ['category' => 'subscription', 'title' => $this->title, 'message' => $this->message, 'business_id' => $this->businessId, 'subscription_request_id' => $this->subscriptionRequestId];
    }
}
