<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyPermissionsUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Business $company)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'company_permission',
            'title' => 'Company Permissions Updated',
            'message' => 'TradeFlow access permissions were updated for '.$this->company->business_name.'.',
            'business_id' => $this->company->id,
        ];
    }
}
