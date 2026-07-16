<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\UserDetailChangeRequest;
use App\Notifications\UserDetailsChangeDecisionNotification;
use App\Notifications\UserDetailsChangeRequestedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();

        return view('profile.edit', [
            'user' => $user,
            'pendingProfileRequest' => $this->requiresOwnerApproval($user)
                ? UserDetailChangeRequest::where('user_id', $user->id)->where('status', 'Pending')->latest()->first()
                : null,
            'profileChangeRequests' => $user->role === 'business_owner'
                ? UserDetailChangeRequest::with('user')
                    ->where('business_id', $user->business_id)
                    ->whereIn('status', ['Pending', 'Approved'])
                    ->latest()
                    ->get()
                : collect(),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^\d{11}$/'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        if ($this->requiresOwnerApproval($user)) {
            $data = $request->validate([
                'reason' => ['required', 'string', 'min:10', 'max:2000'],
            ]);

            $oldValues = $user->only(['name', 'email', 'phone', 'profile_image']);
            $requestedValues = $request->only(['name', 'email', 'phone']);
            $requestedValues['remove_image'] = $request->boolean('remove_image');
            $pendingRequest = UserDetailChangeRequest::where('user_id', $user->id)->where('status', 'Pending')->first();

            if ($request->hasFile('profile_image')) {
                $requestedValues['profile_image'] = $request->file('profile_image')->store('user-detail-change-requests', 'public');
            }

            $hasChanges = collect(['name', 'email', 'phone'])->contains(
                fn (string $field) => (string) ($requestedValues[$field] ?? '') !== (string) ($oldValues[$field] ?? '')
            ) || !empty($requestedValues['profile_image']) || ($requestedValues['remove_image'] && !empty($oldValues['profile_image']));

            if (! $hasChanges) {
                if (!empty($requestedValues['profile_image'])) {
                    Storage::disk('public')->delete($requestedValues['profile_image']);
                }

                return back()->withErrors(['profile' => 'Enter at least one profile change before submitting your request.']);
            }

            if ($pendingRequest?->requested_values['profile_image'] ?? false) {
                $oldRequestImage = $pendingRequest->requested_values['profile_image'];
                if ($oldRequestImage !== ($requestedValues['profile_image'] ?? null)) {
                    Storage::disk('public')->delete($oldRequestImage);
                }
            }

            $changeRequest = UserDetailChangeRequest::updateOrCreate(
                ['user_id' => $user->id, 'status' => 'Pending'],
                [
                    'business_id' => $user->business_id,
                    'old_values' => $oldValues,
                    'requested_values' => $requestedValues,
                    'reason' => $data['reason'],
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ]
            );

            $this->auditProfileRequest($request, $changeRequest, 'profile_details_change_requested', $oldValues, $requestedValues, $user->name.' requested profile-detail changes from the Business Owner.');
            $user->business?->owner?->notify(new UserDetailsChangeRequestedNotification($changeRequest->load('user')));

            return back()->with('success', 'Your profile-change request was sent to the Business Owner. Your details will remain unchanged until it is approved and applied.');
        }

        if ($request->boolean('remove_image') && $user->profile_image) {
            if (Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $user->profile_image = null;
        }

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            Storage::disk('public')->makeDirectory('profile_images');
            $path = $request->file('profile_image')->store('profile_images', 'public');
            abort_unless($path, 422, 'Profile image could not be saved. Please try again.');
            $user->profile_image = $path;
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->save();

        return back()->with('success', 'Profile updated.');
    }

    public function approveUserDetailChangeRequest(Request $request, UserDetailChangeRequest $changeRequest)
    {
        $this->ensureOwnerControlsRequest($changeRequest);
        abort_unless($changeRequest->status === 'Pending', 422, 'Only pending requests can be approved.');

        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $changeRequest->update([
            'status' => 'Approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);
        $this->auditProfileRequest($request, $changeRequest, 'profile_details_change_approved', null, null, auth()->user()->name.' approved '.$changeRequest->user->name.'\'s profile-change request.');

        return back()->with('success', 'Profile-change request approved. Review it once more, then apply the requested changes.');
    }

    public function applyUserDetailChangeRequest(Request $request, UserDetailChangeRequest $changeRequest)
    {
        $this->ensureOwnerControlsRequest($changeRequest);
        abort_unless($changeRequest->status === 'Approved', 422, 'Approve the request before applying it.');

        $user = $changeRequest->user;
        abort_unless($user && $user->business_id === auth()->user()->business_id, 404);
        $requested = $changeRequest->requested_values ?? [];
        validator($requested, [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^\d{11}$/'],
        ])->validate();

        $oldValues = $user->only(['name', 'email', 'phone', 'profile_image']);
        $newValues = [
            'name' => $requested['name'],
            'email' => $requested['email'],
            'phone' => $requested['phone'] ?? null,
        ];
        $requestedImage = $requested['profile_image'] ?? null;
        if ($requestedImage) {
            if ($user->profile_image && $user->profile_image !== $requestedImage) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $newValues['profile_image'] = $requestedImage;
        } elseif (!empty($requested['remove_image']) && $user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
            $newValues['profile_image'] = null;
        }

        $user->update($newValues);
        $changeRequest->update(['status' => 'Applied', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        $this->auditProfileRequest($request, $changeRequest, 'profile_details_change_applied', $oldValues, $user->fresh()->only(['name', 'email', 'phone', 'profile_image']), auth()->user()->name.' applied '.$user->name.'\'s approved profile-change request.');
        $user->notify(new UserDetailsChangeDecisionNotification($changeRequest->fresh()));

        return back()->with('success', 'Requested profile changes were applied and the user was notified.');
    }

    public function rejectUserDetailChangeRequest(Request $request, UserDetailChangeRequest $changeRequest)
    {
        $this->ensureOwnerControlsRequest($changeRequest);
        abort_unless(in_array($changeRequest->status, ['Pending', 'Approved'], true), 422, 'This request is no longer awaiting a decision.');

        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        $requestedImage = $changeRequest->requested_values['profile_image'] ?? null;
        if ($requestedImage) {
            Storage::disk('public')->delete($requestedImage);
        }
        $changeRequest->update([
            'status' => 'Rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);
        $this->auditProfileRequest($request, $changeRequest, 'profile_details_change_rejected', null, null, auth()->user()->name.' rejected '.$changeRequest->user->name.'\'s profile-change request.');
        $changeRequest->user?->notify(new UserDetailsChangeDecisionNotification($changeRequest->fresh()));

        return back()->with('success', 'Profile-change request rejected and the user was notified.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        auth()->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password changed.');
    }

    private function requiresOwnerApproval($user): bool
    {
        return (bool) $user->business_id && $user->role !== 'business_owner' && ! $user->isSuperAdmin();
    }

    private function ensureOwnerControlsRequest(UserDetailChangeRequest $changeRequest): void
    {
        $owner = auth()->user();
        abort_unless($owner->role === 'business_owner' && $owner->business_id === $changeRequest->business_id, 403);
    }

    private function auditProfileRequest(Request $request, UserDetailChangeRequest $changeRequest, string $action, ?array $oldValues, ?array $newValues, string $description): void
    {
        AuditLog::create([
            'business_id' => $changeRequest->business_id,
            'target_user_id' => $changeRequest->user_id,
            'module' => 'Roles & Users',
            'action' => $action,
            'record_type' => 'UserDetailChangeRequest',
            'record_id' => $changeRequest->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
