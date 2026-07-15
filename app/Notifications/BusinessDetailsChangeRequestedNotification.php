<?php

namespace App\Notifications;

use App\Models\BusinessDetailChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BusinessDetailsChangeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly BusinessDetailChangeRequest $changeRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'business_detail_change_request',
            'title' => 'Business Details Change Request',
            'message' => $this->changeRequest->business->business_name.' requested protected business-detail changes.',
            'business_id' => $this->changeRequest->business_id,
            'change_request_id' => $this->changeRequest->id,
        ];
    }
}
