<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessFooterChangeRequest;
use App\Notifications\FooterChangeDecisionNotification;
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

    public function index(Request $request)
    {
        $requests = BusinessFooterChangeRequest::with(['business.owner', 'requester', 'reviewer'])
            ->when($request->integer('business_id'), fn ($query, int $id) => $query->where('business_id', $id))
            ->when($request->integer('request_id'), fn ($query, int $id) => $query->whereKey($id))
            ->when($request->filled('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('super-admin.companies.footer-change-requests', [
            'requests' => $requests,
            'businesses' => Business::orderBy('business_name')->get(['id', 'business_name']),
            'fields' => $this->changes->fields(),
        ]);
    }

    public function review(Request $request, BusinessFooterChangeRequest $footerChangeRequest)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['Approved', 'Rejected', 'Changes Requested'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $updated = DB::transaction(function () use ($footerChangeRequest, $data, $request) {
            $record = BusinessFooterChangeRequest::lockForUpdate()->findOrFail($footerChangeRequest->id);
            if ($record->status !== 'Pending') {
                throw ValidationException::withMessages(['request' => 'This footer change request has already been reviewed.']);
            }

            $business = Business::with('owner')->lockForUpdate()->findOrFail($record->business_id);
            $footer = app(BusinessDocumentFooterService::class)->for($business);
            $old = ['field' => $record->field, 'value' => $this->changes->currentValue($business, $footer, $record->field)];

            if ($data['decision'] === 'Approved') {
                $this->changes->apply($business, $footer, $record->field, $record->requested_value);
            }

            $record->update([
                'status' => $data['decision'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);
            $this->audit($request, $record, 'Footer Change '.($data['decision'] === 'Approved' ? 'Approved' : ($data['decision'] === 'Rejected' ? 'Rejected' : 'Changes Requested')), $old, ['field' => $record->field, 'value' => $record->requested_value]);

            return $record->fresh(['business.owner', 'requester']);
        });

        $updated->requester?->notify(new FooterChangeDecisionNotification($updated));

        return back()->with('success', 'Footer change request '.$data['decision'].'.');
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
