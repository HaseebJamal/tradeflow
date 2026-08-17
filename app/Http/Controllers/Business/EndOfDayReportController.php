<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\BusinessActivityService;
use App\Services\CompanyPermissionService;
use App\Services\DailyClosingReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EndOfDayReportController extends Controller
{
    public function __construct(
        private readonly DailyClosingReportService $dailyClosing,
        private readonly CompanyPermissionService $permissions,
        private readonly BusinessActivityService $activity,
    ) {}

    public function index(Request $request)
    {
        $this->authorize($request);
        $date = $this->date($request);

        return view('business.reports.end-of-day', $this->payload($request, $date));
    }

    public function pdf(Request $request)
    {
        $this->authorize($request, true);
        $date = $this->date($request);
        $payload = $this->payload($request, $date);
        $this->activity->record((int) $request->user()->business_id, 'Reports', 'End of Day Report exported', null, null, [
            'date' => $date->toDateString(),
        ]);

        return Pdf::loadView('business.reports.end-of-day-pdf', $payload + [
            'business' => $request->user()->business?->load(['documentFooter', 'owner:id,email']),
            'generatedAt' => now(config('app.timezone')),
        ])->setPaper('a4')->stream('End-of-Day-'.$date->format('Y-m-d').'.pdf');
    }

    private function payload(Request $request, Carbon $date): array
    {
        $user = $request->user();
        $canPurchases = $this->permissions->allowsUser($user, 'purchases.view');
        $canPos = $this->permissions->allowsUser($user, 'pos.view');

        return [
            'report' => $this->dailyClosing->forDate((int) $user->business_id, $date, [
                'purchases' => $canPurchases,
                'pos' => $canPos,
            ]),
            'selectedDate' => $date,
            'canExport' => $this->permissions->allowsUser($user, 'reports.export'),
            'canPurchases' => $canPurchases,
            'canPos' => $canPos,
        ];
    }

    private function date(Request $request): Carbon
    {
        $data = $request->validate(['date' => ['nullable', 'date']]);

        return filled($data['date'] ?? null)
            ? Carbon::parse($data['date'], config('app.timezone'))->startOfDay()
            : now(config('app.timezone'))->startOfDay();
    }

    private function authorize(Request $request, bool $export = false): void
    {
        $user = $request->user();
        abort_unless(
            $this->permissions->allowsUser($user, 'reports.view')
            && $this->permissions->allowsUser($user, 'accounting.view')
            && $this->permissions->allowsUser($user, 'sales.view')
            && $this->permissions->allowsUser($user, 'expenses.view'),
            403,
        );
        if ($export) {
            abort_unless($this->permissions->allowsUser($user, 'reports.export'), 403);
        }
    }
}
