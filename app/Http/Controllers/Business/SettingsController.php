<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BusinessDetailChangeRequest;
use App\Models\User;
use App\Services\BusinessDocumentFooterService;
use App\Services\CompanyPermissionService;
use App\Notifications\BusinessDetailsChangeRequestedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        $business = auth()->user()->business;
        $canManageDocumentFooter = auth()->user()?->role === 'business_owner'
            && app(CompanyPermissionService::class)->allowsUser(auth()->user(), 'footer_settings.view', $business);

        return view('business.settings.index', [
            'business' => $business,
            'canManageDocumentFooter' => $canManageDocumentFooter,
            'pendingRequest' => $business
                ? BusinessDetailChangeRequest::where('business_id', $business->id)->where('status', 'Pending')->latest()->first()
                : null,
        ]);
    }

    public function editDocumentFooter()
    {
        $this->ensureFooterOwner();
        $business = auth()->user()->business;
        abort_unless($business, 404);

        $footer = app(BusinessDocumentFooterService::class)->for($business);
        if ($footer->wasRecentlyCreated) {
            app(\App\Services\BusinessActivityService::class)->record(
                $business->id, 'Settings', 'Footer Settings Created', $footer->id, null, ['changed_fields' => ['default_footer']]
            );
        }

        return view('business.settings.document-footer', [
            'business' => $business,
            'footer' => $footer,
        ]);
    }

    public function updateDocumentFooter(Request $request)
    {
        $this->ensureFooterOwner();
        $business = auth()->user()->business;
        abort_unless($business, 404);

        $data = $request->validate([
            'footer_title' => ['nullable', 'string', 'max:255'],
            'footer_message' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'show_company_name' => ['nullable', 'boolean'],
            'show_footer_title' => ['nullable', 'boolean'],
            'show_footer_message' => ['nullable', 'boolean'],
            'show_phone' => ['nullable', 'boolean'],
            'show_address' => ['nullable', 'boolean'],
            'show_email' => ['nullable', 'boolean'],
            'show_website' => ['nullable', 'boolean'],
            'show_tax_number' => ['nullable', 'boolean'],
            'show_powered_by' => ['nullable', 'boolean'],
        ]);

        [$footer, $changed] = DB::transaction(function () use ($business, $data, $request): array {
            $lockedBusiness = \App\Models\Business::lockForUpdate()->findOrFail($business->id);
            $footer = app(BusinessDocumentFooterService::class)->for($lockedBusiness);
            $businessFields = ['phone', 'address', 'website', 'tax_number'];
            $footerFields = ['footer_title', 'footer_message', 'show_company_name', 'show_footer_title', 'show_footer_message', 'show_phone', 'show_address', 'show_email', 'show_website', 'show_tax_number', 'show_powered_by'];
            $old = [
                ...$lockedBusiness->only($businessFields),
                ...$footer->only($footerFields),
            ];

            $lockedBusiness->fill(collect($data)->only($businessFields)->map(fn ($value) => $value === '' ? null : $value)->all())->save();
            $footer->fill([
                'footer_title' => filled($data['footer_title'] ?? null) ? trim($data['footer_title']) : null,
                'footer_message' => filled($data['footer_message'] ?? null) ? trim($data['footer_message']) : null,
                'show_company_name' => $request->boolean('show_company_name'),
                'show_footer_title' => $request->boolean('show_footer_title'),
                'show_footer_message' => $request->boolean('show_footer_message'),
                'show_phone' => $request->boolean('show_phone'),
                'show_address' => $request->boolean('show_address'),
                'show_email' => $request->boolean('show_email'),
                'show_website' => $request->boolean('show_website'),
                'show_tax_number' => $request->boolean('show_tax_number'),
                'show_powered_by' => $request->boolean('show_powered_by'),
            ])->save();

            $new = [
                ...$lockedBusiness->fresh()->only($businessFields),
                ...$footer->fresh()->only($footerFields),
            ];
            $changed = array_keys(array_filter($new, fn ($value, string $field) => (string) $value !== (string) ($old[$field] ?? ''), ARRAY_FILTER_USE_BOTH));

            return [$footer, $changed];
        });

        if ($changed !== []) {
            app(\App\Services\BusinessActivityService::class)->record(
                $business->id,
                'Settings',
                'Footer Settings Updated',
                $footer->id,
                null,
                ['changed_fields' => $changed]
            );
        }

        return redirect()->route('business.settings.document-footer.edit')->with('success', 'Receipt footer settings updated.');
    }

    private function ensureFooterOwner(): void
    {
        abort_unless(auth()->user()?->role === 'business_owner', 403, 'Only the Business Owner can manage footer settings.');
    }

    public function updateBusiness(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'phone' => ['required', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'category' => ['nullable', 'string', 'max:100'],
            'owner_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $business = auth()->user()->business;
        abort_unless($business, 404);

        $requestedValues = collect($data)->only(['business_name', 'phone', 'address', 'city', 'category', 'owner_email'])->all();
        if (($requestedValues['owner_email'] ?? null) === $business->owner?->email) {
            unset($requestedValues['owner_email']);
        }

        $protectedFields = ['business_name', 'phone', 'address', 'city', 'category'];
        $oldValues = $business->only($protectedFields);
        if (array_key_exists('owner_email', $requestedValues)) {
            $oldValues['owner_email'] = $business->owner?->email;
        }
        $hasChanges = collect($requestedValues)->contains(fn ($value, string $field) => (string) $value !== (string) ($oldValues[$field] ?? ''));
        if (!$hasChanges) {
            return back()->withErrors(['business' => 'Enter at least one changed business detail before submitting a request.']);
        }

        $changeRequest = BusinessDetailChangeRequest::updateOrCreate(
            ['business_id' => $business->id, 'status' => 'Pending'],
            [
                'requester_id' => auth()->id(),
                'old_values' => $oldValues,
                'requested_values' => $requestedValues,
                'reason' => $data['reason'],
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]
        );

        AuditLog::create([
            'user_id' => auth()->id(),
            'actor_id' => auth()->id(),
            'actor_role' => auth()->user()->role,
            'business_id' => $business->id,
            'module' => 'Settings',
            'action' => 'business_details_change_requested',
            'record_id' => $changeRequest->id,
            'description' => auth()->user()->name.' requested protected business-detail changes.',
            // Login identifiers remain private in audit logs. The pending
            // request itself retains the submitted value solely for the
            // approval workflow to apply after approval.
            'old_values' => collect($oldValues)->except('owner_email')->all(),
            'new_values' => collect($requestedValues)->except('owner_email')->all(),
            'ip_address' => app(\App\Services\AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        User::where('role', 'super_admin')->where('status', 'active')->get()
            ->each(fn (User $admin) => $admin->notify(new BusinessDetailsChangeRequestedNotification($changeRequest->load('business'))));

        return back()->with('success', 'Your business-details change request was submitted to Super Admin for review.');
    }

    public function updateLogo(Request $request)
    {
        $data = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $business = auth()->user()->business;
        abort_unless($business, 404);

        if (!$request->hasFile('logo') && !$request->boolean('remove_logo')) {
            return back()->withErrors(['logo' => 'Choose a logo to upload or select Remove Logo.']);
        }

        $oldLogo = $business->logo;
        $newLogo = $oldLogo;
        if ($request->hasFile('logo')) {
            $newLogo = $request->file('logo')->store('business-logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $newLogo = null;
        }

        $business->update(['logo' => $newLogo]);
        if ($oldLogo && $oldLogo !== $newLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'actor_id' => auth()->id(),
            'actor_role' => auth()->user()->role,
            'business_id' => $business->id,
            'module' => 'Settings',
            'action' => 'business_logo_updated',
            'description' => auth()->user()->name.' updated the company logo.',
            'old_values' => ['logo_present' => (bool) $oldLogo],
            'new_values' => ['logo_present' => (bool) $newLogo],
            'ip_address' => app(\App\Services\AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return back()->with('success', $newLogo ? 'Company logo updated.' : 'Company logo removed.');
    }
}
