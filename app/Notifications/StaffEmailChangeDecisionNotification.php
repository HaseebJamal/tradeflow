<?php

namespace App\Notifications;

use App\Models\EmailChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StaffEmailChangeDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly EmailChangeRequest $changeRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        $title = match ($this->changeRequest->status) {
            'Approved' => 'Email Change Approved',
            'Rejected' => 'Email Change Rejected',
            default => 'Email Change Needs Updates',
        };

        return [
            'category' => 'staff_email_change_decision',
            'title' => $title,
            'message' => match ($this->changeRequest->status) {
                'Approved' => 'Your login email has been updated.',
                'Rejected' => 'Your email-change request was rejected.',
                default => 'Your email-change request needs changes.',
            }.($this->changeRequest->review_note ? ' Note: '.$this->changeRequest->review_note : ''),
            'business_id' => $this->changeRequest->business_id,
            'email_change_request_id' => $this->changeRequest->id,
            'status' => $this->changeRequest->status,
        ];
    }
}
