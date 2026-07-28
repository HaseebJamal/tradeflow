<?php

namespace App\Notifications;

use App\Models\BusinessFooterChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FooterChangeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly BusinessFooterChangeRequest $changeRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'footer_change_request',
            'title' => 'Footer Detail Change Request',
            'message' => $this->changeRequest->business->business_name.' requested a footer detail change.',
            'business_id' => $this->changeRequest->business_id,
            'footer_change_request_id' => $this->changeRequest->id,
        ];
    }
}
