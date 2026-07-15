<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BusinessDetailChangeRequest;
use App\Models\User;
use App\Notifications\BusinessDetailsChangeRequestedNotification;
use Illuminate\Http\Request;

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
            'phone' => ['required', 'regex:/^\d{11}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'category' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $business = auth()->user()->business;
        abort_unless($business, 404);
        abort_unless(auth()->user()->role === 'business_owner', 403);

        $requestedValues = collect($data)->only(['business_name', 'phone', 'address', 'city', 'category'])->all();
        if ($request->hasFile('logo')) {
            $requestedValues['logo'] = $request->file('logo')->store('business-change-requests', 'public');
        }

        $protectedFields = ['business_name', 'phone', 'address', 'city', 'category', 'logo'];
        $oldValues = $business->only($protectedFields);
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
            'old_values' => $oldValues,
            'new_values' => $requestedValues,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        User::where('role', 'super_admin')->where('status', 'active')->get()
            ->each(fn (User $admin) => $admin->notify(new BusinessDetailsChangeRequestedNotification($changeRequest->load('business'))));

        return back()->with('success', 'Your business-details change request was submitted to Super Admin for review.');
    }
}
