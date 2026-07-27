<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierAdvanceApplication extends Model
{
    protected $fillable = ['business_id', 'supplier_id', 'purchase_id', 'supplier_payment_id', 'goods_receipt_id', 'amount', 'created_by'];
    public function payment() { return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id'); }
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
}
