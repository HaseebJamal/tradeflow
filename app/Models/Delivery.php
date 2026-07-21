<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = ['business_id', 'invoice_id', 'order_id', 'delivery_staff_id', 'customer_id', 'address', 'amount', 'payment_status', 'status', 'assigned_at', 'proof_image', 'signature_image', 'receiver_name', 'receiver_phone', 'collected_amount', 'received_amount', 'payment_method', 'payment_reference', 'payment_proof_image', 'payment_proof', 'received_by', 'received_at', 'failure_reason', 'started_at', 'delivered_at', 'failed_at', 'returned_at', 'cancelled_at', 'note', 'created_by'];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'returned_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function staff() { return $this->belongsTo(User::class, 'delivery_staff_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }

    public function sourceOrder(): ?Order
    {
        return $this->invoice?->order ?: $this->order;
    }

    public function sourceInvoice(): ?Invoice
    {
        return $this->invoice ?: $this->order?->invoice;
    }
}
