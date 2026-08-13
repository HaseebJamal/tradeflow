<?php

namespace App\Notifications;

use App\Models\BusinessDetailChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BusinessDetailsChangeDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly BusinessDetailChangeRequest $changeRequest) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        $approved = in_array(strtolower($this->changeRequest->status), ['approved', 'applied'], true);
        return [
            'category' => 'business_detail_change_decision',
            'title' => $approved ? 'Business details updated' : 'Business Details Change Rejected',
            'message' => $approved
                ? 'Your requested business details change has been approved.'
                : 'Your requested business-detail changes were rejected.'.($this->changeRequest->review_note ? ' Note: '.$this->changeRequest->review_note : ''),
            'business_id' => $this->changeRequest->business_id,
            'change_request_id' => $this->changeRequest->id,
            'status' => $this->changeRequest->status,
        ];
    }
}
