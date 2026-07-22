<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BusinessDetailChangeRequest;
use App\Models\User;
use App\Notifications\BusinessDetailsChangeRequestedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        $business = auth()->user()->business;

        return view('business.settings.index', [
            'business' => $business,
            'pendingRequest' => $business
                ? BusinessDetailChangeRequest::where('business_id', $business->id)->where('status', 'Pending')->latest()->first()
                : null,
        ]);
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
