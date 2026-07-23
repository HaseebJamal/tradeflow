<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuotationItem extends Model
{
    protected $fillable = ['sales_quotation_id', 'product_id', 'product_name_snapshot', 'quantity', 'unit_price', 'discount_type', 'discount_value', 'discount_amount', 'tax_type', 'tax_value', 'tax_amount', 'line_total'];

    public function quotation() { return $this->belongsTo(SalesQuotation::class, 'sales_quotation_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
