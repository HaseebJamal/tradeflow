<?php

namespace App\Notifications;

use App\Models\EmailChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StaffEmailChangeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly EmailChangeRequest $changeRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'staff_email_change_request',
            'title' => 'Email Change Requested',
            'message' => $this->changeRequest->user->name.' requested a login email change.',
            'business_id' => $this->changeRequest->business_id,
            'email_change_request_id' => $this->changeRequest->id,
            'user_id' => $this->changeRequest->user_id,
        ];
    }
}
