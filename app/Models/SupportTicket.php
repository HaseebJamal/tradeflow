<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number', 'business_id', 'user_id', 'created_by', 'assigned_admin_id', 'assigned_sub_admin_id',
        'type', 'subject', 'message', 'description', 'admin_reply', 'priority', 'status', 'resolution',
        'first_response_at', 'resolved_at', 'closed_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function assignedAdmin() { return $this->belongsTo(User::class, 'assigned_admin_id'); }
    public function assignedSubAdmin() { return $this->belongsTo(User::class, 'assigned_sub_admin_id'); }
    public function messages() { return $this->hasMany(TicketMessage::class, 'ticket_id'); }
}
