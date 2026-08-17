<?php

namespace Tests\Unit;

use App\Services\DocumentNumberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    private array $documentTables = [
        'document_number_counters', 'orders', 'invoices', 'held_pos_sales',
        'purchases', 'purchase_invoices', 'sales_returns', 'purchase_returns',
        'goods_receipts', 'payments', 'stock_counts',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (array_reverse($this->documentTables) as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('document_number_counters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('scope');
            $table->date('number_date');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'scope', 'number_date']);
        });

        foreach ([
            'orders' => 'order_number', 'invoices' => 'invoice_number', 'held_pos_sales' => 'hold_number',
            'purchases' => 'purchase_number', 'purchase_invoices' => 'invoice_number',
            'sales_returns' => 'return_number', 'purchase_returns' => 'return_number',
            'goods_receipts' => 'grn_number', 'payments' => 'reference_number', 'stock_counts' => 'reference',
        ] as $tableName => $column) {
            Schema::create($tableName, function (Blueprint $table) use ($column): void {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->string($column)->nullable();
            });
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->documentTables) as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_numbers_are_padded_per_business_and_per_document_type(): void
    {
        $numbers = app(DocumentNumberService::class);

        $this->assertSame('INV-000001', $numbers->next(10, 'sales'));
        $this->assertSame('INV-000002', $numbers->next(10, 'sales'));
        $this->assertSame('INV-000001', $numbers->next(11, 'sales'));
        $this->assertSame('PINV-000001', $numbers->next(10, 'purchase'));
        $this->assertSame('SR-000001', $numbers->next(10, 'sales_return'));
        $this->assertSame('PR-000001', $numbers->next(10, 'purchase_return'));
        $this->assertSame('HOLD-000001', $numbers->next(10, 'pos_hold'));
        $this->assertSame('GRN-000001', $numbers->next(10, 'goods_receipt'));
        $this->assertSame('RCPT-000001', $numbers->next(10, 'payment'));
        $this->assertSame('STK-000001', $numbers->next(10, 'stock_count'));
    }

    public function test_existing_standard_numbers_are_not_reused(): void
    {
        DB::table('orders')->insert(['business_id' => 10, 'order_number' => 'INV-000099']);

        $this->assertSame('INV-000100', app(DocumentNumberService::class)->next(10, 'sales'));
    }
}
