<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'unit_name',
        'unit_name_normalized',
        'short_code',
        'unit_type',
        'status',
        'description',
        'created_by',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $unit): void {
            $unit->unit_name = self::normalizeName((string) $unit->unit_name);
            $unit->unit_name_normalized = strtolower($unit->unit_name);
        });
    }

    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $name));
    }
}
