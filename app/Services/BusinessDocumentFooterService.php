<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessDocumentFooter;

class BusinessDocumentFooterService
{
    /** Return the one authoritative footer record, creating safe defaults for legacy companies. */
    public function for(Business $business): BusinessDocumentFooter
    {
        if ($business->relationLoaded('documentFooter') && $business->documentFooter) {
            return $business->documentFooter;
        }

        $footer = BusinessDocumentFooter::firstOrCreate(
            ['business_id' => $business->id],
            $this->defaults($business)
        );

        $business->setRelation('documentFooter', $footer);

        return $footer;
    }

    /** @return array<string, mixed> */
    public function defaults(Business $business): array
    {
        return [
            'footer_title' => $business->business_name,
            'footer_message' => 'Thank you for your business!',
            'show_company_name' => true,
            'show_address' => true,
            'show_phone' => true,
            'show_email' => true,
            'show_website' => true,
            'show_tax_number' => true,
            'show_powered_by' => true,
            'powered_by_text' => 'Powered by TradeFlow',
        ];
    }

    public function reset(Business $business): BusinessDocumentFooter
    {
        $footer = $this->for($business);
        $footer->fill($this->defaults($business))->save();
        $business->setRelation('documentFooter', $footer);

        return $footer;
    }
}
