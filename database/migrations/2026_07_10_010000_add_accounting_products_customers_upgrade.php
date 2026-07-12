<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->string('code');
                $table->string('name');
                $table->string('account_type');
                $table->string('normal_balance');
                $table->string('status')->default('Active');
                $table->timestamps();
                $table->unique(['business_id', 'code']);
                $table->index(['business_id', 'account_type', 'status']);
            });
        }

        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->string('voucher_number');
                $table->date('entry_date');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->unique(['business_id', 'voucher_number']);
                $table->index(['business_id', 'entry_date', 'status']);
            });
        }

        if (!Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->constrained()->restrictOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('debit', 14, 2)->default(0);
                $table->decimal('credit', 14, 2)->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->index(['account_id', 'customer_id', 'product_id']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'opening_stock')) {
                $table->integer('opening_stock')->default(0)->after('purchase_cost');
            }
            if (!Schema::hasColumn('products', 'current_stock')) {
                $table->integer('current_stock')->default(0)->after('opening_stock');
            }
            if (!Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('expiry_date');
            }
            if (!Schema::hasColumn('products', 'brand')) {
                $table->string('brand')->nullable()->after('description');
            }
            if (!Schema::hasColumn('products', 'manufacturer')) {
                $table->string('manufacturer')->nullable()->after('brand');
            }
            if (!Schema::hasColumn('products', 'warehouse_location')) {
                $table->string('warehouse_location')->nullable()->after('manufacturer');
            }
            if (!Schema::hasColumn('products', 'has_batch_tracking')) {
                $table->boolean('has_batch_tracking')->default(false)->after('warehouse_location');
            }
            if (!Schema::hasColumn('products', 'manufacturing_date')) {
                $table->date('manufacturing_date')->nullable()->after('batch_number');
            }
            if (!Schema::hasColumn('products', 'expiry_alert_days')) {
                $table->unsignedInteger('expiry_alert_days')->nullable()->after('expiry_date');
            }
            if (!Schema::hasColumn('products', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('customers', 'province')) {
                $table->string('province')->nullable()->after('city');
            }
            if (!Schema::hasColumn('customers', 'opening_balance')) {
                $table->decimal('opening_balance', 14, 2)->default(0)->after('credit_limit');
            }
            if (!Schema::hasColumn('customers', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('customers', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
