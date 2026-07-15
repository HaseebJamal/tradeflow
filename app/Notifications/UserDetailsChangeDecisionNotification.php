<?php

namespace App\Notifications;

use App\Models\UserDetailChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserDetailsChangeDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly UserDetailChangeRequest $changeRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        $applied = strtolower($this->changeRequest->status) === 'applied';

        return [
            'category' => 'user_detail_change_decision',
            'title' => $applied ? 'Profile Change Applied' : 'Profile Change Rejected',
            'message' => $applied
                ? 'Your requested profile changes were approved and applied by your Business Owner.'
                : 'Your requested profile changes were rejected.'.($this->changeRequest->review_note ? ' Note: '.$this->changeRequest->review_note : ''),
            'business_id' => $this->changeRequest->business_id,
            'change_request_id' => $this->changeRequest->id,
            'status' => $this->changeRequest->status,
        ];
    }
}
