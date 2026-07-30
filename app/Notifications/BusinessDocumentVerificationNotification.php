<?php

namespace App\Notifications;

use App\Models\BusinessDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BusinessDocumentVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly BusinessDocument $document) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = str_replace('_', ' ', ucfirst($this->document->document_type));
        $status = $this->document->status;
        $reason = $status === 'Rejected'
            ? $this->document->rejection_reason
            : ($status === 'Re-upload Requested' ? $this->document->reupload_reason : null);

        return [
            'category' => 'business_document_verification',
            'title' => $label.' '.$status,
            'message' => $reason
                ? 'Your '.$label.' was marked '.$status.'. Reason: '.$reason
                : 'Your '.$label.' was verified.',
            'business_id' => $this->document->business_id,
            'document_id' => $this->document->id,
            'status' => $status,
        ];
    }
}
