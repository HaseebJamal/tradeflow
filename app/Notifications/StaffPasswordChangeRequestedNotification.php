<?php

namespace App\Notifications;

use App\Models\StaffPasswordChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StaffPasswordChangeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly StaffPasswordChangeRequest $passwordRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'staff_password_change_request',
            'title' => 'Staff Password Change Request',
            'message' => $this->passwordRequest->user->name.' requested a password change.',
            'business_id' => $this->passwordRequest->business_id,
            'password_change_request_id' => $this->passwordRequest->id,
            'user_id' => $this->passwordRequest->user_id,
        ];
    }
}
