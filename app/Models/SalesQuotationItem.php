<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuotationItem extends Model
{
    protected $fillable = ['sales_quotation_id', 'product_id', 'product_name_snapshot', 'quantity', 'unit_price', 'line_total'];

    public function quotation() { return $this->belongsTo(SalesQuotation::class, 'sales_quotation_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
