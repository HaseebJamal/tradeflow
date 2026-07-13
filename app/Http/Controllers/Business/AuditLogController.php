<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        [$filters, $query] = $this->filteredQuery($request);

        return view('business.audit-logs.index', [
            'logs' => $query->latest('occurred_at')->paginate(30)->withQueryString(),
            'users' => User::where('business_id', $this->businessId())->orderBy('name')->get(['id', 'name', 'role']),
            'modules' => $this->businessLogs()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            'actions' => $this->businessLogs()->whereNotNull('action')->distinct()->orderBy('action')->pluck('action'),
            'filters' => $filters,
        ]);
    }

    public function live(Request $request)
    {
        $validated = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $afterId = max(0, (int) ($validated['after_id'] ?? 0));
        $canViewDetails = app(\App\Services\CompanyPermissionService::class)->allowsUser(auth()->user(), 'audit_logs.view_details');
        $logs = $this->businessLogs()
            ->where('id', '>', $afterId)
            ->latest('id')
            ->take(50)
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (AuditLog $log) => $this->payload($log, $canViewDetails));

        return response()->json(['logs' => $logs, 'last_id' => $logs->last()['id'] ?? $afterId]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [, $query] = $this->filteredQuery($request);
        $filename = 'tradeflow-audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date & Time', 'User', 'Role', 'Module', 'Action', 'Description', 'Route', 'IP Address', 'Record']);
            $query->latest('occurred_at')->chunkById(250, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        ($log->occurred_at ?? $log->created_at)
                            ? Carbon::parse($log->occurred_at ?? $log->created_at)
                                ->timezone(config('app.timezone'))
                                ->format('d M, Y h:i A')
                            : '—',
                        $log->user_name ?: $log->user?->name ?: 'System',
                        $log->role ?: $log->actor_role,
                        $log->module,
                        $log->action,
                        $log->description,
                        $log->route,
                        $log->ip_address,
                        trim(($log->record_type ?: '').' #'.($log->record_id ?: '')),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request)
    {
        [, $query] = $this->filteredQuery($request);
        $logs = $query->latest('occurred_at')->limit(1000)->get();

        return Pdf::loadView('business.audit-logs.pdf', compact('logs'))->setPaper('a4', 'landscape')
            ->stream('tradeflow-audit-logs-'.now()->format('Ymd-His').'.pdf');
    }

    private function filteredQuery(Request $request): array
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'], 'role' => ['nullable', 'string', 'max:100'], 'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:255'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'], 'ip_address' => ['nullable', 'ip'],
        ]);
        $filters += ['date_from' => null, 'date_to' => null];
        if (!$filters['date_from'] && !$filters['date_to']) {
            $filters['date_from'] = now()->toDateString();
            $filters['date_to'] = now()->toDateString();
        }
        $query = $this->businessLogs()->with('user')
            ->when($filters['user_id'] ?? null, fn ($q, $value) => $q->where('user_id', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where('role', $value))
            ->when($filters['module'] ?? null, fn ($q, $value) => $q->where('module', $value))
            ->when($filters['action'] ?? null, fn ($q, $value) => $q->where('action', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('occurred_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('occurred_at', '<=', $value))
            ->when($filters['ip_address'] ?? null, fn ($q, $value) => $q->where('ip_address', $value))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner->where('description', 'like', "%{$value}%")->orWhere('route', 'like', "%{$value}%")->orWhere('action', 'like', "%{$value}%")));

        return [$filters, $query];
    }

    private function businessLogs()
    {
        $platformRoles = ['super_admin', 'platform_admin', 'platform_sub_admin'];

        return AuditLog::query()
            ->where('business_id', $this->businessId())
            ->where(fn ($query) => $query->whereNull('role')->orWhereNotIn('role', $platformRoles))
            ->where(fn ($query) => $query->whereNull('actor_role')->orWhereNotIn('actor_role', $platformRoles));
    }

    private function payload(AuditLog $log, bool $includeDetails = true): array
    {
        return [
            'id' => $log->id,
            'occurred_at' => ($log->occurred_at ?? $log->created_at)
                ? Carbon::parse($log->occurred_at ?? $log->created_at)
                    ->timezone(config('app.timezone'))
                    ->format('d M, Y h:i A')
                : '—',
            'user' => $log->user_name ?: $log->user?->name ?: 'System', 'role' => $log->role ?: $log->actor_role ?: 'system',
            'module' => $log->module ?: 'General', 'action' => $log->action, 'description' => $log->description ?: $log->action,
            'ip_address' => $log->ip_address ?: '—', 'has_details' => $includeDetails,
            'route' => $includeDetails ? $log->route : null, 'record_type' => $includeDetails ? $log->record_type : null, 'record_id' => $includeDetails ? $log->record_id : null,
            'old_values' => $includeDetails ? $log->old_values : null, 'new_values' => $includeDetails ? $log->new_values : null, 'user_agent' => $includeDetails ? $log->user_agent : null,
        ];
    }

    private function businessId(): int { return (int) auth()->user()->business_id; }
}
