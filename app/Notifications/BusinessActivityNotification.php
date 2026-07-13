<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BusinessActivityNotification extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $details */
    public function __construct(
        private readonly Business $business,
        private readonly string $module,
        private readonly string $action,
        private readonly ?int $recordId = null,
        private readonly array $details = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'business_activity',
            'title' => $this->module.' activity: '.$this->business->business_name,
            'message' => $this->action,
            'business_id' => $this->business->id,
            'module' => $this->module,
            'record_id' => $this->recordId,
            'details' => $this->details,
        ];
    }
}
