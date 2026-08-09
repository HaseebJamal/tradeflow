<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EmailChangeRequest;
use App\Models\StaffPasswordChangeRequest;
use App\Models\UserDetailChangeRequest;
use App\Models\User;
use App\Notifications\StaffEmailChangeDecisionNotification;
use App\Notifications\StaffEmailChangeRequestedNotification;
use App\Notifications\StaffPasswordChangeDecisionNotification;
use App\Notifications\StaffPasswordChangeRequestedNotification;
use App\Notifications\UserDetailsChangeDecisionNotification;
use App\Notifications\UserDetailsChangeRequestedNotification;
use App\Services\CompanyPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $canApproveEmailChanges = $this->canApproveEmailChanges($user);

        return view('profile.edit', [
            'user' => $user,
            'pendingProfileRequest' => $this->requiresOwnerApproval($user)
                ? UserDetailChangeRequest::where('user_id', $user->id)->where('status', 'Pending')->latest()->first()
                : null,
            'pendingEmailChangeRequest' => $this->requiresOwnerApproval($user)
                ? EmailChangeRequest::where('user_id', $user->id)->where('status', 'Pending')->latest()->first()
                : null,
            'profileChangeRequests' => $user->role === 'business_owner'
                ? UserDetailChangeRequest::with('user')
                    ->where('business_id', $user->business_id)
                    ->whereIn('status', ['Pending', 'Approved'])
                    ->latest()
                    ->get()
                : collect(),
            'emailChangeRequests' => $canApproveEmailChanges
                ? EmailChangeRequest::with('user')
                    ->where('business_id', $user->business_id)
                    ->whereIn('status', ['Pending', 'Changes Requested'])
                    ->latest()
                    ->get()
                : collect(),
            'canApproveEmailChanges' => $canApproveEmailChanges,
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $rules = [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
        if (! $this->requiresOwnerApproval($user)) {
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }
        $data = $request->validate($rules, [
            'phone.regex' => 'Enter a valid international phone number including its country code.',
        ]);

        if ($this->requiresOwnerApproval($user)) {
            $oldValues = $user->only(['name', 'email', 'phone']);
            // Staff email is intentionally excluded from this profile path.
            // It can only change through the separately authorized request.
            $requestedValues = [
                'name' => $data['name'],
                'email' => $user->email,
                'phone' => $data['phone'] ?? null,
            ];
            $hasDetailChanges = collect(['name', 'phone'])->contains(
                fn (string $field) => (string) ($requestedValues[$field] ?? '') !== (string) ($oldValues[$field] ?? '')
            );
            $hasImageChange = $request->hasFile('profile_image') || ($request->boolean('remove_image') && $user->profile_image);

            $data = $hasDetailChanges ? $request->validate([
                'reason' => ['required', 'string', 'min:10', 'max:2000'],
            ]) : [];

            // Profile images are personal account data. Staff can manage their
            // own avatar immediately; only identity/contact changes require
            // Business Owner review.
            if ($hasImageChange) {
                $oldImage = $user->profile_image;
                if ($request->hasFile('profile_image')) {
                    $user->profile_image = $request->file('profile_image')->store('profile_images', 'public');
                } elseif ($request->boolean('remove_image')) {
                    $user->profile_image = null;
                }
                $user->save();
                if ($oldImage && $oldImage !== $user->profile_image) {
                    Storage::disk('public')->delete($oldImage);
                }
            }

            if (! $hasDetailChanges) {
                if ($hasImageChange) {
                    return back()->with('success', 'Profile image updated.');
                }
                return back()->withErrors(['profile' => 'Enter at least one profile change before submitting your request.']);
            }

            $pendingRequest = UserDetailChangeRequest::where('user_id', $user->id)->where('status', 'Pending')->first();

            if ($pendingRequest?->requested_values['profile_image'] ?? false) {
                $oldRequestImage = $pendingRequest->requested_values['profile_image'];
                Storage::disk('public')->delete($oldRequestImage);
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

        $oldImage = $user->profile_image;

        if ($request->hasFile('profile_image')) {
            Storage::disk('public')->makeDirectory('profile_images');
            $path = $request->file('profile_image')->store('profile_images', 'public');
            abort_unless($path, 422, 'Profile image could not be saved. Please try again.');

            if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                Storage::disk('public')->delete($oldImage);
            }
            $user->profile_image = $path;
        } elseif ($request->boolean('remove_image') && $oldImage) {
            if (Storage::disk('public')->exists($oldImage)) {
                Storage::disk('public')->delete($oldImage);
            }
            $user->profile_image = null;
        }

        // This path is only for the authenticated user. Explicitly fill the
        // three editable identity fields so no role, permission, password, or
        // account-scoping data can be changed by a profile submission.
        $user->forceFill([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => filled($data['phone'] ?? null) ? $data['phone'] : null,
        ])->save();

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    public function requestEmailChange(Request $request)
    {
        $user = $request->user();
        abort_unless($this->requiresOwnerApproval($user), 403);

        $data = $request->validate([
            'current_email' => ['required', 'email', 'max:255'],
            'requested_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $currentEmail = strtolower(trim($data['current_email']));
        $requestedEmail = strtolower(trim($data['requested_email']));

        if ($currentEmail !== strtolower((string) $user->email)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_email' => 'The current email does not match your account.',
            ]);
        }
        if ($requestedEmail === $currentEmail) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'requested_email' => 'Enter a different email address.',
            ]);
        }

        $changeRequest = DB::transaction(function () use ($user, $currentEmail, $requestedEmail, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $pending = EmailChangeRequest::where('business_id', $user->business_id)
                ->where('user_id', $user->id)
                ->where('status', 'Pending')
                ->lockForUpdate()
                ->first();

            if ($pending) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'requested_email' => 'An email-change request is already awaiting review.',
                ]);
            }

            $revision = EmailChangeRequest::where('business_id', $user->business_id)
                ->where('user_id', $user->id)
                ->where('status', 'Changes Requested')
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($revision) {
                $revision->update([
                    'current_email' => $currentEmail,
                    'requested_email' => $requestedEmail,
                    'reason' => $data['reason'],
                    'status' => 'Pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ]);

                return $revision->fresh('user');
            }

            return EmailChangeRequest::create([
                'business_id' => $user->business_id,
                'user_id' => $user->id,
                'current_email' => $currentEmail,
                'requested_email' => $requestedEmail,
                'reason' => $data['reason'],
                'status' => 'Pending',
            ])->load('user');
        });

        $this->emailApprovers($user)->each(
            fn (User $approver) => $approver->notify(new StaffEmailChangeRequestedNotification($changeRequest))
        );
        $this->auditEmailChange($request, $changeRequest, 'email_change_requested', $user->name.' requested a login email change.');

        return back()->with('success', 'Your email-change request was sent for review.');
    }

    public function approveEmailChangeRequest(Request $request, EmailChangeRequest $changeRequest)
    {
        $this->ensureEmailChangeApprover($changeRequest);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);

        [$changeRequest, $staff] = DB::transaction(function () use ($changeRequest, $data) {
            $lockedRequest = EmailChangeRequest::where('business_id', auth()->user()->business_id)
                ->lockForUpdate()
                ->findOrFail($changeRequest->id);
            abort_unless($lockedRequest->status === 'Pending', 422, 'Only pending email-change requests can be approved.');

            $staff = User::where('business_id', $lockedRequest->business_id)
                ->lockForUpdate()
                ->findOrFail($lockedRequest->user_id);
            abort_unless($staff->role !== 'business_owner' && ! $staff->isSuperAdmin(), 404);

            if (strtolower((string) $staff->email) !== strtolower((string) $lockedRequest->current_email)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email_change' => 'The staff email changed after this request. Ask the staff member to submit a new request.',
                ]);
            }

            validator(['email' => $lockedRequest->requested_email], [
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            ])->validate();

            $staff->update(['email' => strtolower(trim($lockedRequest->requested_email))]);
            $lockedRequest->update([
                'status' => 'Approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);

            return [$lockedRequest->fresh('user'), $staff->fresh()];
        });

        $this->auditEmailChange($request, $changeRequest, 'email_change_approved', auth()->user()->name.' approved a staff login email change.');
        $staff->notify(new StaffEmailChangeDecisionNotification($changeRequest));

        return back()->with('success', 'Email-change request approved and the staff member was notified.');
    }

    public function rejectEmailChangeRequest(Request $request, EmailChangeRequest $changeRequest)
    {
        $this->ensureEmailChangeApprover($changeRequest);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);

        $changeRequest->update([
            'status' => 'Rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);
        $this->auditEmailChange($request, $changeRequest, 'email_change_rejected', auth()->user()->name.' rejected a staff login email-change request.');
        $changeRequest->user?->notify(new StaffEmailChangeDecisionNotification($changeRequest->fresh()));

        return back()->with('success', 'Email-change request rejected and the staff member was notified.');
    }

    public function requestEmailChangeChanges(Request $request, EmailChangeRequest $changeRequest)
    {
        $this->ensureEmailChangeApprover($changeRequest);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        abort_unless($changeRequest->status === 'Pending', 422, 'Only pending email-change requests can be revised.');

        $changeRequest->update([
            'status' => 'Changes Requested',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);
        $this->auditEmailChange($request, $changeRequest, 'email_change_changes_requested', auth()->user()->name.' requested changes to a staff login email request.');
        $changeRequest->user?->notify(new StaffEmailChangeDecisionNotification($changeRequest->fresh()));

        return back()->with('success', 'Requested changes were sent to the staff member.');
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
            'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
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

        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $requestedImage = $changeRequest->requested_values['profile_image'] ?? null;
        if ($requestedImage) {
            Storage::disk('public')->delete($requestedImage);
        }
        $changeRequest->update([
            'status' => 'Rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);
        $this->auditProfileRequest($request, $changeRequest, 'profile_details_change_rejected', null, null, auth()->user()->name.' rejected '.$changeRequest->user->name.'\'s profile-change request.');
        $changeRequest->user?->notify(new UserDetailsChangeDecisionNotification($changeRequest->fresh()));

        return back()->with('success', 'Profile-change request rejected and the user was notified.');
    }

    public function requestStaffPasswordChange(Request $request)
    {
        $user = auth()->user();
        abort_unless($this->requiresOwnerApproval($user), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $passwordRequest = DB::transaction(function () use ($user, $data) {
            // Lock the staff account itself so concurrent submissions cannot both
            // observe an empty pending-request set.
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $pending = StaffPasswordChangeRequest::where('user_id', $user->id)
                ->where('business_id', $user->business_id)
                ->where('status', 'Pending')
                ->lockForUpdate()
                ->first();

            if ($pending) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'password_request' => 'A password-change request is already pending Business Owner review.',
                ]);
            }

            return StaffPasswordChangeRequest::create([
                'business_id' => $user->business_id,
                'user_id' => $user->id,
                'reason' => $data['reason'],
                'status' => 'Pending',
                'requested_at' => now(),
            ]);
        });

        $this->auditStaffPasswordRequest($request, $passwordRequest, 'staff_password_change_requested', $user->name.' requested a password change from the Business Owner.');
        $user->business?->owner?->notify(new StaffPasswordChangeRequestedNotification($passwordRequest->load('user')));

        return back()->with('success', 'Your password-change request was sent to the Business Owner.');
    }

    public function approveStaffPasswordChangeRequest(Request $request, StaffPasswordChangeRequest $passwordRequest)
    {
        $this->ensureOwnerControlsPasswordRequest($passwordRequest);
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ], ['password.confirmed' => 'New password and confirmation do not match.']);

        $passwordRequest = DB::transaction(function () use ($passwordRequest, $data) {
            $lockedRequest = StaffPasswordChangeRequest::where('business_id', auth()->user()->business_id)
                ->lockForUpdate()
                ->findOrFail($passwordRequest->id);
            abort_unless($lockedRequest->status === 'Pending', 422, 'Only pending password-change requests can be approved.');

            $staff = $lockedRequest->user;
            abort_unless($staff && $staff->business_id === auth()->user()->business_id && $staff->role !== 'business_owner' && ! $staff->isSuperAdmin(), 404);
            $staff->update(['password' => Hash::make($data['password'])]);
            $lockedRequest->update([
                'status' => 'Approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);

            return $lockedRequest->fresh('user');
        });

        $this->auditStaffPasswordRequest($request, $passwordRequest, 'staff_password_change_approved', auth()->user()->name.' approved a password change for '.$passwordRequest->user->name.'.');
        $passwordRequest->user->notify(new StaffPasswordChangeDecisionNotification($passwordRequest));

        return back()->with('success', 'Password-change request approved and the staff member was notified.');
    }

    public function rejectStaffPasswordChangeRequest(Request $request, StaffPasswordChangeRequest $passwordRequest)
    {
        $this->ensureOwnerControlsPasswordRequest($passwordRequest);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);

        $passwordRequest = DB::transaction(function () use ($passwordRequest, $data) {
            $lockedRequest = StaffPasswordChangeRequest::where('business_id', auth()->user()->business_id)
                ->lockForUpdate()
                ->findOrFail($passwordRequest->id);
            abort_unless($lockedRequest->status === 'Pending', 422, 'Only pending password-change requests can be rejected.');

            $lockedRequest->update([
                'status' => 'Rejected',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);

            return $lockedRequest->fresh('user');
        });

        $this->auditStaffPasswordRequest($request, $passwordRequest, 'staff_password_change_rejected', auth()->user()->name.' rejected a password-change request from '.$passwordRequest->user->name.'.');
        $passwordRequest->user->notify(new StaffPasswordChangeDecisionNotification($passwordRequest));

        return back()->with('success', 'Password-change request rejected and the staff member was notified.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'current_password.current_password' => 'Current password is incorrect.',
            'password.confirmed' => 'Passwords do not match.',
        ]);
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
        $this->auditPasswordChanged($request, $user);

        return back()->with('success', 'Password changed successfully.');
    }

    private function requiresOwnerApproval($user): bool
    {
        return (bool) $user->business_id && $user->role !== 'business_owner' && ! $user->isSuperAdmin();
    }

    private function canApproveEmailChanges(User $user): bool
    {
        if (! $user->business_id) {
            return false;
        }

        if ($user->role === 'business_owner') {
            return true;
        }

        return app(CompanyPermissionService::class)->allowsUser($user, 'users.approve_email_change', $user->business);
    }

    private function ensureEmailChangeApprover(EmailChangeRequest $changeRequest): void
    {
        $approver = auth()->user();
        abort_unless($approver && $approver->business_id === $changeRequest->business_id, 403);
        abort_unless($this->canApproveEmailChanges($approver), 403);
        abort_unless($changeRequest->user && $changeRequest->user->business_id === $approver->business_id, 404);
    }

    private function emailApprovers(User $requester)
    {
        $business = $requester->business;
        if (! $business) {
            return collect();
        }

        $permissions = app(CompanyPermissionService::class);

        return User::where('business_id', $requester->business_id)
            ->where('status', 'active')
            ->where('id', '!=', $requester->id)
            ->get()
            ->filter(fn (User $candidate) => $candidate->role === 'business_owner'
                || $permissions->allowsUser($candidate, 'users.approve_email_change', $business))
            ->unique('id')
            ->values();
    }

    private function ensureOwnerControlsRequest(UserDetailChangeRequest $changeRequest): void
    {
        $owner = auth()->user();
        abort_unless($owner->role === 'business_owner' && $owner->business_id === $changeRequest->business_id, 403);
    }

    private function ensureOwnerControlsPasswordRequest(StaffPasswordChangeRequest $passwordRequest): void
    {
        $owner = auth()->user();
        abort_unless($owner->role === 'business_owner' && $owner->business_id === $passwordRequest->business_id, 403);
        abort_unless($passwordRequest->user && $passwordRequest->user->business_id === $owner->business_id && $passwordRequest->user->role !== 'business_owner' && ! $passwordRequest->user->isSuperAdmin(), 404);
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
            'ip_address' => app(\App\Services\AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function auditStaffPasswordRequest(Request $request, StaffPasswordChangeRequest $passwordRequest, string $action, string $description): void
    {
        AuditLog::create([
            'business_id' => $passwordRequest->business_id,
            'target_user_id' => $passwordRequest->user_id,
            'module' => 'Roles & Users',
            'action' => $action,
            'record_type' => 'StaffPasswordChangeRequest',
            'record_id' => $passwordRequest->id,
            'description' => $description,
            'new_values' => ['status' => $passwordRequest->status],
            'ip_address' => app(\App\Services\AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function auditPasswordChanged(Request $request, User $user): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'business_id' => $user->business_id,
            'target_user_id' => $user->id,
            'module' => 'Profile',
            'action' => 'password_changed',
            'record_type' => 'User',
            'record_id' => $user->id,
            'description' => $user->name.' changed their password.',
            'ip_address' => app(\App\Services\AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function auditEmailChange(Request $request, EmailChangeRequest $changeRequest, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'actor_id' => auth()->id(),
            'actor_role' => auth()->user()?->role,
            'business_id' => $changeRequest->business_id,
            'target_user_id' => $changeRequest->user_id,
            'module' => 'Roles & Users',
            'action' => $action,
            'record_type' => 'EmailChangeRequest',
            'record_id' => $changeRequest->id,
            'description' => $description,
            'new_values' => ['status' => $changeRequest->status],
            'ip_address' => app(\App\Services\AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
