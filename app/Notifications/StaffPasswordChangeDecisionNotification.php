<?php

namespace App\Notifications;

use App\Models\StaffPasswordChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StaffPasswordChangeDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly StaffPasswordChangeRequest $passwordRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        $approved = $this->passwordRequest->status === 'Approved';

        return [
            'category' => 'staff_password_change_decision',
            'title' => $approved ? 'Password Change Approved' : 'Password Change Rejected',
            'message' => $approved
                ? 'Your Business Owner approved your password-change request. You can now sign in with the new password they set.'
                : 'Your Business Owner rejected your password-change request.'.($this->passwordRequest->review_note ? ' Note: '.$this->passwordRequest->review_note : ''),
            'business_id' => $this->passwordRequest->business_id,
            'password_change_request_id' => $this->passwordRequest->id,
            'status' => $this->passwordRequest->status,
        ];
    }
}
