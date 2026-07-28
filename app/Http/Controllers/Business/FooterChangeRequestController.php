<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessFooterChangeRequest;
use App\Models\User;
use App\Notifications\FooterChangeRequestedNotification;
use App\Services\AuditIpResolver;
use App\Services\BusinessDocumentFooterService;
use App\Services\BusinessFooterChangeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FooterChangeRequestController extends Controller
{
    public function __construct(private readonly BusinessFooterChangeService $changes) {}

    public function store(Request $request)
    {
        $business = $this->ownerBusiness($request);
        $data = $request->validate([
            'field' => ['required', Rule::in(array_keys($this->changes->fields()))],
            'requested_value' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $changeRequest = DB::transaction(function () use ($business, $data, $request) {
            $lockedBusiness = Business::with('owner')->lockForUpdate()->findOrFail($business->id);
            $footer = app(BusinessDocumentFooterService::class)->for($lockedBusiness);
            $requestedValue = $this->changes->normalize($data['field'], $data['requested_value'] ?? null);
            $currentValue = $this->changes->currentValue($lockedBusiness, $footer, $data['field']);

            if ((string) $currentValue === (string) $requestedValue) {
                throw ValidationException::withMessages(['requested_value' => 'The requested value is already active.']);
            }

            if (BusinessFooterChangeRequest::where('business_id', $lockedBusiness->id)
                ->where('field', $data['field'])->where('status', 'Pending')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['field' => 'A pending request already exists for this footer detail.']);
            }

            $record = BusinessFooterChangeRequest::create([
                'business_id' => $lockedBusiness->id,
                'requester_id' => $request->user()->id,
                'field' => $data['field'],
                'current_value' => $currentValue,
                'requested_value' => $requestedValue,
                'reason' => $data['reason'],
                'status' => 'Pending',
            ]);

            $this->audit($request, $record, 'Footer Change Requested', ['field' => $data['field'], 'value' => $currentValue], ['field' => $data['field'], 'value' => $requestedValue]);

            return $record->load('business');
        });

        User::where('role', 'super_admin')->where('status', 'active')->get()
            ->each(fn (User $admin) => $admin->notify(new FooterChangeRequestedNotification($changeRequest)));

        return redirect()->route('business.settings.document-footer.edit')->with('success', 'Footer detail change request submitted for Super Admin review.');
    }

    public function cancel(Request $request, BusinessFooterChangeRequest $footerChangeRequest)
    {
        $business = $this->ownerBusiness($request);
        abort_unless($footerChangeRequest->business_id === $business->id && $footerChangeRequest->requester_id === $request->user()->id, 404);
        abort_unless($footerChangeRequest->status === 'Pending', 422, 'Only pending footer detail change requests can be cancelled.');

        $footerChangeRequest->update(['status' => 'Cancelled', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $this->audit($request, $footerChangeRequest, 'Footer Change Cancelled', null, ['field' => $footerChangeRequest->field]);

        return redirect()->route('business.settings.document-footer.edit')->with('success', 'Footer detail change request cancelled.');
    }

    private function ownerBusiness(Request $request): Business
    {
        abort_unless($request->user()?->role === 'business_owner', 403, 'Only the Business Owner can manage footer settings.');
        $business = $request->user()->business;
        abort_unless($business, 404);

        return $business;
    }

    private function audit(Request $request, BusinessFooterChangeRequest $changeRequest, string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'business_id' => $changeRequest->business_id,
            'module' => 'Settings',
            'action' => $action,
            'record_type' => BusinessFooterChangeRequest::class,
            'record_id' => $changeRequest->id,
            'description' => $action.' for '.$changeRequest->field.'.',
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => app(AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
