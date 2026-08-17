<?php

namespace Tests\Unit;

use App\Services\AgingReportService;
use Carbon\Carbon;
use Tests\TestCase;

class AgingReportServiceTest extends TestCase
{
    public function test_due_date_boundaries_use_the_expected_aging_buckets(): void
    {
        $service = app(AgingReportService::class);
        $asOf = Carbon::parse('2026-08-16', config('app.timezone'));

        $this->assertSame('current', $service->bucketFor(Carbon::parse('2026-08-16'), $asOf));
        $this->assertSame('current', $service->bucketFor(Carbon::parse('2026-08-21'), $asOf));
        $this->assertSame('days_1_30', $service->bucketFor(Carbon::parse('2026-08-15'), $asOf));
        $this->assertSame('days_1_30', $service->bucketFor(Carbon::parse('2026-07-17'), $asOf));
        $this->assertSame('days_31_60', $service->bucketFor(Carbon::parse('2026-07-16'), $asOf));
        $this->assertSame('days_61_90', $service->bucketFor(Carbon::parse('2026-06-16'), $asOf));
        $this->assertSame('days_90_plus', $service->bucketFor(Carbon::parse('2026-05-17'), $asOf));
    }
}
