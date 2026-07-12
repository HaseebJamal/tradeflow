<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyRegistrationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Business $business)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'company_registration',
            'title' => 'New Company Registration',
            'message' => $this->business->business_name.' has registered and is waiting for approval.',
            'business_id' => $this->business->id,
            'status' => $this->business->status,
        ];
    }
}
