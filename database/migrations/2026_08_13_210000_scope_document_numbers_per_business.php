<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_number_counters', function (Blueprint $table): void {
            $table->unsignedBigInteger('business_id')->nullable()->after('id');
        });

        $this->dropUniqueWithColumns('document_number_counters', ['scope', 'number_date']);

        Schema::table('document_number_counters', function (Blueprint $table): void {
            $table->unique(['business_id', 'scope', 'number_date'], 'document_number_counters_business_scope_date_unique');
        });

        $this->replaceGlobalReferenceUnique('orders', 'order_number', 'orders_business_order_number_unique');
        $this->replaceGlobalReferenceUnique('invoices', 'invoice_number', 'invoices_business_invoice_number_unique');
        $this->replaceGlobalReferenceUnique('purchases', 'purchase_number', 'purchases_business_purchase_number_unique');
        $this->replaceGlobalReferenceUnique('purchase_invoices', 'invoice_number', 'purchase_invoices_business_invoice_number_unique');
        $this->replaceGlobalReferenceUnique('purchase_returns', 'return_number', 'purchase_returns_business_return_number_unique');
        $this->replaceGlobalReferenceUnique('sales_returns', 'return_number', 'sales_returns_business_return_number_unique');

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique(['business_id', 'reference_number'], 'payments_business_reference_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_business_reference_number_unique');
        });

        foreach ([
            ['orders', 'orders_business_order_number_unique'],
            ['invoices', 'invoices_business_invoice_number_unique'],
            ['purchases', 'purchases_business_purchase_number_unique'],
            ['purchase_invoices', 'purchase_invoices_business_invoice_number_unique'],
            ['purchase_returns', 'purchase_returns_business_return_number_unique'],
            ['sales_returns', 'sales_returns_business_return_number_unique'],
        ] as [$tableName, $indexName]) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropUnique($indexName);
            });
        }

        Schema::table('document_number_counters', function (Blueprint $table): void {
            $table->dropUnique('document_number_counters_business_scope_date_unique');
            $table->dropColumn('business_id');
        });
    }

    private function replaceGlobalReferenceUnique(string $tableName, string $column, string $newIndex): void
    {
        $this->dropSingleColumnUnique($tableName, $column);

        Schema::table($tableName, function (Blueprint $table) use ($column, $newIndex): void {
            $table->unique(['business_id', $column], $newIndex);
        });
    }

    private function dropSingleColumnUnique(string $tableName, string $column): void
    {
        $this->dropUniqueWithColumns($tableName, [$column]);
    }

    private function dropUniqueWithColumns(string $tableName, array $columns): void
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === $columns) {
                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }
        }
    }
};
