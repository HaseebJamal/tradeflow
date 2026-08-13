<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlDocumentNumberConcurrencyTest extends TestCase
{
    private ?Business $business = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'tradeflow_testing') {
            $this->markTestSkipped('Document-number concurrency coverage runs only against tradeflow_testing.');
        }

        $this->business = Business::query()->create([
            'business_name' => 'QA Numbering '.uniqid(),
            'business_type' => 'General',
            'status' => 'Approved',
        ]);
    }

    protected function tearDown(): void
    {
        $this->business?->delete();

        parent::tearDown();
    }

    public function test_parallel_allocations_are_unique_and_sequential_per_business(): void
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo app(App\Services\DocumentNumberService::class)->next((int) $argv[1], 'sales');
PHP;

        $environment = [
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => storage_path('framework/cache/qa-config-do-not-create.php'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => 'tradeflow_testing',
            'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => config('database.connections.mysql.password'),
        ];

        $first = new Process([PHP_BINARY, '-r', $script, (string) $this->business->id], base_path(), $environment);
        $second = new Process([PHP_BINARY, '-r', $script, (string) $this->business->id], base_path(), $environment);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
        $this->assertSame(['INV-000001', 'INV-000002'], collect([
            trim($first->getOutput()),
            trim($second->getOutput()),
        ])->sort()->values()->all());
    }
}
