<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A deliberately small database notification for an unresolved business
 * condition. Routine activity remains in AuditLog and must not use this.
 */
class ActionableBusinessNotification extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly int $businessId,
        private readonly string $category,
        private readonly string $priority,
        private readonly string $actionableKey,
        private readonly array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'priority' => $this->priority,
            'title' => $this->title,
            'message' => $this->message,
            'business_id' => $this->businessId,
            'actionable_key' => $this->actionableKey,
        ] + $this->context;
    }
}
