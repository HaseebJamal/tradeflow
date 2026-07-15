<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BusinessDetailsUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Business $business, private readonly User $admin) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'business_details_updated',
            'title' => 'Business Details Updated',
            'message' => 'Super Admin updated protected business details for '.$this->business->business_name.'.',
            'business_id' => $this->business->id,
            'updated_by' => $this->admin->name,
        ];
    }
}
