<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessDocumentFooter;
use Illuminate\Http\Request;

class BusinessDocumentFooterService
{
    public function platformPoweredByText(): string
    {
        $platformName = trim((string) app(PlatformSettingsService::class)->current()->company_name);

        return 'Powered by '.($platformName !== '' ? $platformName : config('app.name', 'Profit Point'));
    }

    public function displayedPoweredByText(?BusinessDocumentFooter $footer): string
    {
        // The platform-owned value must follow a platform rename. This also
        // upgrades legacy “Powered by TradeFlow” records without changing
        // each business footer in the database.
        return $this->platformPoweredByText();
    }

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
            'show_footer_title' => true,
            'show_footer_message' => true,
            'show_address' => true,
            'show_phone' => true,
            'show_email' => true,
            'show_website' => true,
            'show_powered_by' => true,
            'powered_by_text' => $this->platformPoweredByText(),
        ];
    }

    public function reset(Business $business): BusinessDocumentFooter
    {
        $footer = $this->for($business);
        $footer->fill($this->defaults($business))->save();
        $business->setRelation('documentFooter', $footer);

        return $footer;
    }

    /**
     * Normalise independently submitted visibility controls. Missing
     * checkboxes deliberately become false, so an uncheck always persists.
     *
     * @return array<string, bool>
     */
    public function visibilityFromRequest(Request $request): array
    {
        $submittedVisibility = $request->input('footer_visibility', []);
        $submittedVisibility = is_array($submittedVisibility) ? $submittedVisibility : [];

        return collect(BusinessDocumentFooter::VISIBILITY_FIELDS)
            ->mapWithKeys(function (string $field) use ($request, $submittedVisibility): array {
                // The receipt-footer form always sends an explicit 0 or 1
                // for each visibility option. Keep the direct-field fallback
                // for older requests while normalizing both paths to booleans.
                $value = array_key_exists($field, $submittedVisibility)
                    ? $submittedVisibility[$field]
                    : $request->input($field, false);

                return [$field => filter_var($value, FILTER_VALIDATE_BOOLEAN)];
            })
            ->all();
    }
}
