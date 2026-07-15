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
        $applied = strtolower($this->changeRequest->status) === 'applied';
        return [
            'category' => 'business_detail_change_decision',
            'title' => $applied ? 'Business Details Change Applied' : 'Business Details Change Rejected',
            'message' => $applied
                ? 'Your requested business-detail changes were approved and have now been applied by Super Admin.'
                : 'Your requested business-detail changes were rejected.'.($this->changeRequest->review_note ? ' Note: '.$this->changeRequest->review_note : ''),
            'business_id' => $this->changeRequest->business_id,
            'change_request_id' => $this->changeRequest->id,
            'status' => $this->changeRequest->status,
        ];
    }
}
