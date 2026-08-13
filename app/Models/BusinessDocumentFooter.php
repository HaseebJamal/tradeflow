<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDocumentFooter extends Model
{
    /**
     * Business-controlled footer visibility options. Platform attribution is
     * intentionally excluded because it is mandatory and set server-side.
     */
    public const VISIBILITY_FIELDS = [
        'show_footer_title',
        'show_footer_message',
        'show_phone',
        'show_email',
        'show_address',
        'show_website',
    ];

    protected $fillable = [
        'business_id', 'footer_title', 'footer_message', 'show_footer_title', 'show_footer_message',
        'show_address', 'show_phone', 'show_email', 'show_website',
        'show_tax_number', 'show_powered_by', 'powered_by_text',
    ];

    protected $casts = [
        'show_footer_title' => 'boolean',
        'show_footer_message' => 'boolean',
        'show_address' => 'boolean',
        'show_phone' => 'boolean',
        'show_email' => 'boolean',
        'show_website' => 'boolean',
        'show_tax_number' => 'boolean',
        'show_powered_by' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
