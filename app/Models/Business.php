<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = ['owner_id', 'business_name', 'business_type', 'category', 'phone', 'address', 'city', 'registration_number', 'tax_number', 'logo', 'status'];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function users() { return $this->hasMany(User::class); }
    public function documents() { return $this->hasMany(BusinessDocument::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
    public function subscription() { return $this->hasOne(Subscription::class); }
    public function reports() { return $this->hasMany(BusinessReport::class); }
}
