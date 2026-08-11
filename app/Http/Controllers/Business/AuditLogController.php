<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditIpResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        [$filters, $query] = $this->filteredQuery($request);
        $businessId = $this->businessId();
        $users = $this->businessUsers()->orderBy('name')->get(['id', 'name', 'role']);
        $metadata = Cache::remember("tradeflow:business:{$businessId}:audit-log-filter-metadata", now()->addMinutes(5), function (): array {
            return [
                'modules' => $this->businessLogs()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module')->all(),
                'actions' => $this->businessLogs()->whereNotNull('action')->distinct()->orderBy('action')->pluck('action')->all(),
            ];
        });

        return view('business.audit-logs.index', [
            'logs' => $query->orderByDesc('created_at')->paginate(10)->withQueryString(),
            'users' => $users,
            'modules' => $metadata['modules'],
            'actions' => $metadata['actions'],
            'filters' => $filters,
        ]);
    }

    public function live(Request $request)
    {
        $validated = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $afterId = max(0, (int) ($validated['after_id'] ?? 0));
        [, $query] = $this->filteredQuery($request);
        $logs = $query
            ->where('id', '>', $afterId)
            ->latest('id')
            ->take(50)
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (AuditLog $log) => $this->payload($log, false));

        return response()->json(['logs' => $logs, 'last_id' => $logs->last()['id'] ?? $afterId]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [, $query] = $this->filteredQuery($request);
        $filename = 'tradeflow-audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date & Time', 'User', 'Role', 'Module', 'Action', 'IP Address']);
            $query->chunkById(250, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        ($log->occurred_at ?? $log->created_at)
                            ? Carbon::parse($log->occurred_at ?? $log->created_at)
                                ->timezone(config('app.timezone'))
                                ->format('d M, Y h:i A')
                            : 'â€”',
                        $log->user_name ?: $log->user?->name ?: 'System',
                        $log->role ?: $log->actor_role,
                        $log->module,
                        $log->action,
                        AuditIpResolver::display($log->ip_address),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request)
    {
        [, $query] = $this->filteredQuery($request);
        $logs = $query->orderByDesc('created_at')->limit(250)->get();

        $business = $request->user()->business?->load(['documentFooter', 'owner:id,email']);

        return Pdf::loadView('business.audit-logs.pdf', compact('logs', 'business'))->setPaper('a4', 'landscape')
            ->stream('tradeflow-audit-logs-'.now()->format('Ymd-His').'.pdf');
    }

    private function filteredQuery(Request $request): array
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'], 'role' => ['nullable', 'string', 'max:100'], 'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date', 'required_with:date_to'],
            'date_to' => ['nullable', 'date', 'required_with:date_from', 'after_or_equal:date_from'],
            'time_from' => ['nullable', 'date_format:H:i'], 'time_to' => ['nullable', 'date_format:H:i'],
            'month' => ['nullable', 'integer', 'between:1,12', 'required_with:year'],
            'year' => ['nullable', 'integer', 'between:2000,2100', 'required_with:month'],
            'search' => ['nullable', 'string', 'max:255'], 'ip_address' => ['nullable', 'ip'],
        ]);
        $filters = array_replace([
            'date_from' => null, 'date_to' => null, 'time_from' => null, 'time_to' => null,
            'month' => null, 'year' => null,
        ], $filters);
        $businessUsers = $this->businessUsers()->get(['id', 'role']);
        $allowedUserIds = $businessUsers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowedRoles = $businessUsers->pluck('role')->filter()->unique()->values()->all();

        if (filled($filters['user_id'] ?? null) && ! in_array((int) $filters['user_id'], $allowedUserIds, true)) {
            throw ValidationException::withMessages(['user_id' => 'The selected user is not available for this business.']);
        }

        if (filled($filters['role'] ?? null) && ! in_array($filters['role'], $allowedRoles, true)) {
            throw ValidationException::withMessages(['role' => 'The selected role is not available for this business.']);
        }
        $hasDateRange = filled($filters['date_from']) && filled($filters['date_to']);
        $hasMonthRange = filled($filters['month']) && filled($filters['year']);

        if ((filled($filters['time_from']) || filled($filters['time_to'])) && !$hasDateRange) {
            throw ValidationException::withMessages(['date_from' => 'Select Date From and Date To before applying a time range.']);
        }

        if ($hasDateRange || $hasMonthRange) {
            [$start, $end] = $this->auditLogPeriod($filters, $hasDateRange);
        } else {
            $start = now()->startOfDay();
            $end = now();
            $filters['date_from'] = $start->toDateString();
            $filters['date_to'] = $end->toDateString();
            $filters['time_to'] = $end->format('H:i');
        }

        $query = $this->businessLogs()
            ->select(['id', 'user_id', 'user_name', 'role', 'actor_role', 'business_id', 'module', 'action', 'description', 'route', 'record_type', 'record_id', 'ip_address', 'occurred_at', 'created_at'])
            ->with('user:id,name')
            ->when($filters['user_id'] ?? null, fn ($q, $value) => $q->where('user_id', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where('role', $value))
            ->when($filters['module'] ?? null, fn ($q, $value) => $q->where('module', $value))
            ->when($filters['action'] ?? null, fn ($q, $value) => $q->where('action', $value))
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['ip_address'] ?? null, fn ($q, $value) => $q->whereIn('ip_address', AuditIpResolver::searchable($value)))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner->where('description', 'like', "%{$value}%")->orWhere('route', 'like', "%{$value}%")->orWhere('action', 'like', "%{$value}%")));

        return [$filters, $query];
    }

    private function auditLogPeriod(array $filters, bool $hasDateRange): array
    {
        $timezone = config('app.timezone');

        if ($hasDateRange) {
            $start = Carbon::parse($filters['date_from'], $timezone)->startOfDay();
            $end = Carbon::parse($filters['date_to'], $timezone)->endOfDay();

            if (filled($filters['time_from'])) {
                $start = Carbon::createFromFormat('Y-m-d H:i', $filters['date_from'].' '.$filters['time_from'], $timezone);
            }

            if (filled($filters['time_to'])) {
                $end = Carbon::createFromFormat('Y-m-d H:i', $filters['date_to'].' '.$filters['time_to'], $timezone);
            }
        } else {
            $start = Carbon::create((int) $filters['year'], (int) $filters['month'], 1, 0, 0, 0, $timezone)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['time_to' => 'Time To must be after Time From.']);
        }

        return [$start, $end];
    }

    private function businessLogs()
    {
        $platformRoles = ['super_admin', 'platform_admin', 'platform_sub_admin'];
        $businessUserIds = $this->businessUsers()->pluck('id')->all();

        return AuditLog::query()
            ->where('business_id', $this->businessId())
            ->where(fn ($query) => $query->whereNull('role')->orWhereNotIn('role', $platformRoles))
            ->where(fn ($query) => $query->whereNull('actor_role')->orWhereNotIn('actor_role', $platformRoles))
            ->where(function ($query) use ($businessUserIds) {
                $query->whereIn('actor_id', $businessUserIds)
                    ->orWhereIn('user_id', $businessUserIds)
                    ->orWhere(function ($system) {
                        $system->whereNull('actor_id')->whereNull('user_id');
                    });
            });
    }

    private function businessUsers()
    {
        $businessId = $this->businessId();

        return User::query()
            ->whereNotIn('role', ['super_admin', 'platform_admin', 'platform_sub_admin'])
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)
                    ->orWhereHas('ownedBusiness', fn ($business) => $business->whereKey($businessId));
            });
    }

    private function payload(AuditLog $log, bool $includeDetails = true): array
    {
        return [
            'id' => $log->id,
            'occurred_at' => ($log->occurred_at ?? $log->created_at)
                ? Carbon::parse($log->occurred_at ?? $log->created_at)
                    ->timezone(config('app.timezone'))
                    ->format('d M, Y h:i A')
                : 'â€”',
            'user' => $log->user_name ?: $log->user?->name ?: 'System', 'role' => $log->role ?: $log->actor_role ?: 'system',
            'module' => $log->module ?: 'General', 'action' => $log->action, 'description' => $log->description ?: $log->action,
            'ip_address' => AuditIpResolver::display($log->ip_address, 'â€”'), 'has_details' => $includeDetails,
            'route' => $includeDetails ? $log->route : null, 'record_type' => $includeDetails ? $log->record_type : null, 'record_id' => $includeDetails ? $log->record_id : null,
            'old_values' => $includeDetails ? $log->old_values : null, 'new_values' => $includeDetails ? $log->new_values : null, 'user_agent' => $includeDetails ? $log->user_agent : null,
        ];
    }

    private function businessId(): int { return (int) auth()->user()->business_id; }
}
