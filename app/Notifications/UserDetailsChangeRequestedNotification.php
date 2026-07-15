<?php

namespace App\Notifications;

use App\Models\UserDetailChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserDetailsChangeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly UserDetailChangeRequest $changeRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'user_detail_change_request',
            'title' => 'User Detail Change Request',
            'message' => $this->changeRequest->user->name.' requested changes to their profile details.',
            'business_id' => $this->changeRequest->business_id,
            'change_request_id' => $this->changeRequest->id,
            'user_id' => $this->changeRequest->user_id,
        ];
    }
}
