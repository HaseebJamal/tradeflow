<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'sender_id', 'message', 'attachment', 'internal_note'];

    protected $casts = ['internal_note' => 'boolean'];

    public function ticket() { return $this->belongsTo(SupportTicket::class); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
}
