<?php

namespace App\Notifications;

use App\Models\BusinessFooterChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FooterChangeDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly BusinessFooterChangeRequest $changeRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        $status = $this->changeRequest->status;
        return [
            'category' => 'footer_change_decision',
            'title' => 'Footer Change '.$status,
            'message' => 'Your requested '.$this->changeRequest->field.' footer detail change was '.strtolower($status).'.'.($this->changeRequest->review_note ? ' '.$this->changeRequest->review_note : ''),
            'business_id' => $this->changeRequest->business_id,
            'footer_change_request_id' => $this->changeRequest->id,
            'status' => $status,
        ];
    }
}
