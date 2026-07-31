<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BusinessDetailChangeRequest;
use App\Models\BusinessFooterChangeRequest;
use App\Models\EmailChangeRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionChangeRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserDetailChangeRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessRequestIndexService
{
    public const SOURCES = ['subscription', 'footer', 'business_detail', 'email', 'profile'];

    private const ACTIONABLE_SUBSCRIPTION_TYPES = [
        'New Subscription',
        'Upgrade',
        'Downgrade',
        'Billing Cycle Change',
        'Payment Method Change',
        'Renewal',
        'Cancellation',
        'Resume Cancellation',
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = DB::query()
            ->fromSub($this->union(), 'business_requests')
            ->join('businesses', 'businesses.id', '=', 'business_requests.business_id')
            ->leftJoin('users as owners', 'owners.id', '=', 'businesses.owner_id')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'business_requests.reviewed_by')
            ->select([
                'business_requests.*',
                'businesses.business_name',
                'owners.name as owner_name',
                'reviewers.name as reviewer_name',
            ]);

        if ($filters['search'] ?? null) {
            $search = $filters['search'];
            $query->where(function (Builder $query) use ($search): void {
                $query->where('business_requests.id', 'like', "%{$search}%")
                    ->orWhere('businesses.business_name', 'like', "%{$search}%")
                    ->orWhere('owners.name', 'like', "%{$search}%");
            });
        }

        $query
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('business_requests.source', $source))
            ->when($filters['request_id'] ?? null, fn (Builder $query, int $id) => $query->where('business_requests.id', $id))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('business_requests.request_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('business_requests.status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('business_requests.requested_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('business_requests.requested_at', '<=', $date));

        $requests = $query->orderByDesc('business_requests.requested_at')->paginate(10)->withQueryString();

        $requests->getCollection()->transform(function (object $request): object {
            $request->change_summary = $this->changeSummary(
                $request->source,
                $request->current_value,
                $request->requested_value,
            );

            return $request;
        });

        return $requests;
    }

    public function pendingCount(): int
    {
        return DB::query()->fromSub($this->union(), 'business_requests')
            // "Changes Requested" has already been reviewed and is waiting
            // on the business owner. Legacy generic "Subscription" rows are
            // historical registration records, not current request-queue work.
            ->where('status', 'Pending')
            ->where(function (Builder $query): void {
                $query->where('source', '!=', 'subscription')
                    ->orWhereIn('request_type', self::ACTIONABLE_SUBSCRIPTION_TYPES);
            })
            ->count();
    }

    public function requestTypes(): array
    {
        return [
            'New Subscription', 'Upgrade', 'Downgrade', 'Billing Cycle Change', 'Payment Method Change',
            'Renewal', 'Cancellation', 'Resume Cancellation', 'Footer Detail Change',
            'Business Information Change', 'Email Change', 'Profile Detail Change',
        ];
    }

    public function detail(string $source, int $id): array
    {
        $record = $this->record($source, $id);
        $record->loadMissing($this->relationsFor($source));

        [$type, $current, $requested, $reason, $amount, $cycle, $paymentMethod] = match ($source) {
            'subscription' => [
                $record->type,
                $record->currentPlan?->name ?? 'No current plan',
                $record->requestedPlan?->name ?? 'Not provided',
                $record->note,
                $record->expected_amount,
                $record->billing_cycle,
                $record->payment_method,
            ],
            'footer' => [
                'Footer Detail Change',
                $record->field.': '.($record->current_value ?: 'Not provided'),
                $record->field.': '.($record->requested_value ?: 'Not provided'),
                $record->reason,
                null,
                null,
                null,
            ],
            'business_detail' => [
                'Business Information Change',
                $this->formatValues($record->old_values),
                $this->formatValues($record->requested_values),
                $record->reason,
                null,
                null,
                null,
            ],
            'email' => [
                'Email Change',
                $record->current_email,
                $record->requested_email,
                $record->reason,
                null,
                null,
                null,
            ],
            'profile' => [
                'Profile Detail Change',
                $this->formatValues($record->old_values),
                $this->formatValues($record->requested_values),
                $record->reason,
                null,
                null,
            ],
        };

        $changes = in_array($source, ['business_detail', 'profile'], true)
            ? $this->changesFor($source, $record->old_values, $record->requested_values)
            : $this->changesFor($source, $current, $requested);

        $details = $this->detailValues($source, $record, $current, $requested, $amount, $cycle, $paymentMethod);

        return [
            'id' => $record->id,
            'source' => $source,
            'business' => $record->business?->business_name ?? 'Business unavailable',
            'owner' => $record->business?->owner?->name ?? 'Owner not assigned',
            'requested_by' => $record->requester?->name ?? $record->user?->name ?? 'Business user',
            'type' => $type,
            'current_value' => $current,
            'requested_value' => $requested,
            'changes' => $changes,
            'reason' => $reason,
            'billing_cycle' => $cycle,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'status' => $record->status,
            'requested_at' => optional($record->created_at)->format('d M, Y h:i A'),
            'reviewed_by' => $record->reviewer?->name,
            'reviewed_at' => optional($record->reviewed_at)->format('d M, Y h:i A'),
            'review_note' => $record->review_note ?? $record->admin_note ?? null,
            'current_details' => $details['current'],
            'requested_details' => $details['requested'],
            'payment_details' => $details['payment'],
            'actions' => $this->actionsFor($source, $record),
            'history' => $this->history($record),
        ];
    }

    private function union(): Builder
    {
        $queries = [
            DB::table('subscription_change_requests')->selectRaw("id, 'subscription' as source, business_id, requested_by as requester_id, type as request_type, COALESCE((SELECT name FROM subscription_plans WHERE subscription_plans.id = subscription_change_requests.current_plan_id), 'No current plan') as current_value, COALESCE((SELECT name FROM subscription_plans WHERE subscription_plans.id = subscription_change_requests.requested_plan_id), 'Not provided') as requested_value, status, reviewed_by, reviewed_at, created_at as requested_at"),
            DB::table('business_footer_change_requests')->selectRaw("id, 'footer' as source, business_id, requester_id, 'Footer Detail Change' as request_type, current_value, requested_value, status, reviewed_by, reviewed_at, created_at as requested_at"),
            DB::table('business_detail_change_requests')->selectRaw("id, 'business_detail' as source, business_id, requester_id, 'Business Information Change' as request_type, old_values as current_value, requested_values as requested_value, status, reviewed_by, reviewed_at, created_at as requested_at"),
            DB::table('email_change_requests')->selectRaw("id, 'email' as source, business_id, user_id as requester_id, 'Email Change' as request_type, current_email as current_value, requested_email as requested_value, status, reviewed_by, reviewed_at, created_at as requested_at"),
            DB::table('user_detail_change_requests')->selectRaw("id, 'profile' as source, business_id, user_id as requester_id, 'Profile Detail Change' as request_type, old_values as current_value, requested_values as requested_value, status, reviewed_by, reviewed_at, created_at as requested_at"),
        ];

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function record(string $source, int $id): object
    {
        if (! in_array($source, self::SOURCES, true)) {
            throw ValidationException::withMessages(['request' => 'The related request is no longer available.']);
        }

        $model = match ($source) {
            'subscription' => SubscriptionChangeRequest::class,
            'footer' => BusinessFooterChangeRequest::class,
            'business_detail' => BusinessDetailChangeRequest::class,
            'email' => EmailChangeRequest::class,
            'profile' => UserDetailChangeRequest::class,
        };

        return $model::findOrFail($id);
    }

    private function relationsFor(string $source): array
    {
        return match ($source) {
            'subscription' => ['business.owner', 'business.subscription.plan', 'requester', 'reviewer', 'currentPlan', 'requestedPlan', 'subscription.plan'],
            'footer', 'business_detail' => ['business.owner', 'requester', 'reviewer'],
            'email', 'profile' => ['business.owner', 'user', 'reviewer'],
        };
    }

    private function actionsFor(string $source, object $record): array
    {
        if (! in_array($record->status, ['Pending', 'Changes Requested'], true)) {
            return [];
        }

        return match ($source) {
            'subscription' => [
                'url' => route('admin.subscription-change-requests.review', $record),
                'decisions' => ['Approved', 'Rejected', 'Changes Requested'],
                'note_field' => 'admin_note',
            ],
            'footer' => [
                'url' => route('admin.footer-change-requests.review', $record),
                'decisions' => ['Approved', 'Rejected', 'Changes Requested'],
                'note_field' => 'review_note',
            ],
            'business_detail' => [
                'decisions' => ['Approved', 'Rejected'],
                'decision_urls' => [
                    'Approved' => route('admin.business-detail-change-requests.approve', $record),
                    'Rejected' => route('admin.business-detail-change-requests.reject', $record),
                ],
                'note_field' => 'review_note',
            ],
            default => [],
        };
    }

    private function history(object $record): array
    {
        $history = [[
            'status' => 'Submitted',
            'performed_by' => $record->requester?->name ?? $record->user?->name ?? 'Business user',
            'at' => optional($record->created_at)->format('d M, Y h:i A'),
            'message' => null,
        ]];

        if ($record->reviewed_at) {
            $history[] = [
                'status' => $record->status,
                'performed_by' => $record->reviewer?->name ?? 'Reviewer',
                'at' => $record->reviewed_at->format('d M, Y h:i A'),
                'message' => $record->review_note ?? $record->admin_note ?? null,
            ];
        }

        $auditTypes = [get_class($record), class_basename($record)];
        AuditLog::query()
            ->where('business_id', $record->business_id)
            ->where('record_id', $record->id)
            ->whereIn('record_type', $auditTypes)
            ->oldest()
            ->get(['action', 'description', 'created_at'])
            ->each(function (AuditLog $audit) use (&$history): void {
                $history[] = [
                    'status' => str($audit->action)->headline()->toString(),
                    'performed_by' => 'Audit record',
                    'at' => optional($audit->created_at)->format('d M, Y h:i A'),
                    'message' => $audit->description,
                ];
            });

        return collect($history)->unique(fn (array $item) => implode('|', [$item['status'], $item['at'], $item['message']]))->values()->all();
    }

    private function detailValues(string $source, object $record, mixed $current, mixed $requested, mixed $amount, mixed $cycle, mixed $paymentMethod): array
    {
        if ($source === 'subscription') {
            $subscription = $record->subscription ?? $record->business?->subscription;
            $currentPlan = $subscription?->plan ?? $record->currentPlan;
            $requestedPlan = $record->requestedPlan;
            $businessId = $record->business_id;
            $usage = [
                'Products used' => Product::where('business_id', $businessId)->count(),
                'Staff used' => User::where('business_id', $businessId)->where('role', '!=', 'business_owner')->where('status', '!=', 'archived')->count(),
                'Orders used' => Order::where('business_id', $businessId)->count(),
            ];

            $currentDetails = [
                'Plan' => $currentPlan?->name ?? 'No current plan',
                'Billing cycle' => $subscription?->billing_cycle,
                'Price' => $subscription ? 'Rs '.number_format((float) $subscription->amount, 2) : null,
                'Subscription status' => $subscription?->status,
                'Start date' => $subscription?->starts_at?->format('d M, Y'),
                'Expiry date' => $subscription?->ends_at?->format('d M, Y'),
                'Days remaining' => $subscription?->ends_at ? max(0, today()->diffInDays($subscription->ends_at, false)).' days' : null,
                'Product limit' => $this->limitValue($currentPlan?->product_limit),
                'Staff limit' => $this->limitValue($currentPlan?->staff_limit),
                'Order limit' => $this->limitValue($currentPlan?->order_limit),
            ] + $usage;

            $requestedDetails = [
                'Plan' => $requestedPlan?->name ?? $requested,
                'Billing cycle' => $cycle,
                'Amount' => $amount !== null ? 'Rs '.number_format((float) $amount, 2) : null,
                'Payment method' => $paymentMethod,
                'Effective date' => $record->effective_at?->format('d M, Y'),
                'Product limit' => $this->limitValue($requestedPlan?->product_limit),
                'Staff limit' => $this->limitValue($requestedPlan?->staff_limit),
                'Order limit' => $this->limitValue($requestedPlan?->order_limit),
            ];

            return [
                'current' => $this->withoutEmpty($currentDetails),
                'requested' => $this->withoutEmpty($requestedDetails),
                'payment' => $this->withoutEmpty([
                    'Payment method' => $paymentMethod,
                    'Expected amount' => $amount !== null ? 'Rs '.number_format((float) $amount, 2) : null,
                    'Payment status' => $subscription?->payment_status,
                    'Payment reference' => $subscription?->payment_reference,
                ]),
            ];
        }

        if (in_array($source, ['business_detail', 'profile'], true)) {
            return [
                'current' => $this->labelValues($record->old_values ?? []),
                'requested' => $this->labelValues($record->requested_values ?? []),
                'payment' => [],
            ];
        }

        return [
            'current' => $this->withoutEmpty(['Current value' => $current]),
            'requested' => $this->withoutEmpty(['Requested value' => $requested]),
            'payment' => [],
        ];
    }

    private function labelValues(array $values): array
    {
        return collect($values)
            ->mapWithKeys(fn ($value, $key) => [$this->fieldLabel((string) $key) => $this->displayValue($value)])
            ->all();
    }

    private function withoutEmpty(array $values): array
    {
        return collect($values)->filter(fn ($value) => filled($value))->all();
    }

    private function limitValue(mixed $limit): ?string
    {
        return $limit === null || (int) $limit <= 0 ? 'Unlimited' : number_format((int) $limit);
    }

    private function formatValues(mixed $values): string
    {
        if (! is_array($values)) {
            return filled($values) ? (string) $values : 'Not provided';
        }

        return collect($values)
            ->reject(fn ($value) => blank($value))
            ->map(fn ($value, $key) => str($key)->headline().': '.(is_scalar($value) ? $value : json_encode($value)))
            ->implode("\n") ?: 'Not provided';
    }

    /**
     * Keep the list compact while retaining every value in the request-details modal.
     */
    private function changeSummary(string $source, mixed $current, mixed $requested): string
    {
        if ($source === 'subscription') {
            return trim((string) $current).' to '.trim((string) $requested);
        }

        $fields = array_keys($this->changesFor($source, $current, $requested));

        if ($fields === []) {
            return 'View requested details';
        }

        $visible = array_slice($fields, 0, 3);
        $summary = implode(', ', $visible);

        return count($fields) > 3
            ? $summary.' +'.(count($fields) - 3).' more'
            : $summary;
    }

    private function changesFor(string $source, mixed $current, mixed $requested): array
    {
        if (in_array($source, ['business_detail', 'profile'], true)) {
            $oldValues = $this->decodeValues($current);
            $requestedValues = $this->decodeValues($requested);
            $changes = [];

            foreach (array_unique(array_merge(array_keys($oldValues), array_keys($requestedValues))) as $key) {
                $oldValue = $oldValues[$key] ?? null;
                $requestedValue = $requestedValues[$key] ?? null;

                if ($oldValue === $requestedValue || (blank($oldValue) && blank($requestedValue))) {
                    continue;
                }

                $changes[$this->fieldLabel((string) $key)] = [
                    'current' => $this->displayValue($oldValue),
                    'requested' => $this->displayValue($requestedValue),
                ];
            }

            return $changes;
        }

        return match ($source) {
            'footer' => [
                'Footer detail' => [
                    'current' => $this->displayValue($current),
                    'requested' => $this->displayValue($requested),
                ],
            ],
            'email' => [
                'Login email' => [
                    'current' => $this->displayValue($current),
                    'requested' => $this->displayValue($requested),
                ],
            ],
            'subscription' => [
                'Plan' => [
                    'current' => $this->displayValue($current),
                    'requested' => $this->displayValue($requested),
                ],
            ],
            default => [],
        };
    }

    private function decodeValues(mixed $values): array
    {
        if (is_array($values)) {
            return $values;
        }

        if (! is_string($values) || blank($values)) {
            return [];
        }

        $decoded = json_decode($values, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'business_name' => 'Business name',
            'owner_name', 'name' => 'Name',
            'phone', 'owner_phone' => 'Phone',
            'email', 'owner_email' => 'Email',
            'tax_number', 'ntn_number' => 'NTN / Tax number',
            'logo', 'profile_image' => 'Image',
            default => str($field)->replace('_', ' ')->headline()->toString(),
        };
    }

    private function displayValue(mixed $value): string
    {
        if (blank($value)) {
            return 'Not provided';
        }

        if (is_string($value) && str_contains($value, '/')) {
            return basename($value);
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value) ?: 'Not provided';
    }
}
