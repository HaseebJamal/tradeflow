<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('businesses', 'archived_by')) {
                $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('businesses', 'archived_status')) {
                $table->string('archived_status')->nullable()->after('archived_by');
            }
        });

        if (!Schema::hasTable('permission_definitions')) Schema::create('permission_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('permission_key')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        if (!Schema::hasTable('company_permissions')) Schema::create('company_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('permission_key');
            $table->boolean('allowed')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'permission_key'], 'cp_company_permission_unique');
        });

        if (!Schema::hasTable('permission_templates')) Schema::create('permission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        if (!Schema::hasTable('permission_template_items')) Schema::create('permission_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_template_id')->constrained()->cascadeOnDelete();
            $table->string('permission_key');
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['permission_template_id', 'permission_key'], 'pti_template_permission_unique');
        });
        elseif (!Schema::hasIndex('permission_template_items', 'pti_template_permission_unique')) Schema::table('permission_template_items', function (Blueprint $table) {
            $table->unique(['permission_template_id', 'permission_key'], 'pti_template_permission_unique');
        });

        if (!Schema::hasTable('company_approval_histories')) Schema::create('company_approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        $definitions = [
            'dashboard.view' => ['dashboard', 'View Dashboard'],
            'products.view' => ['products', 'View Products'], 'products.create' => ['products', 'Create Products'], 'products.edit' => ['products', 'Edit Products'], 'products.delete' => ['products', 'Delete Products'], 'products.bulk_import' => ['products', 'Bulk Import Products'], 'products.export' => ['products', 'Export Products'],
            'inventory.view' => ['inventory', 'View Inventory'], 'inventory.add_stock' => ['inventory', 'Add Stock'], 'inventory.adjust_stock' => ['inventory', 'Adjust Stock'], 'inventory.view_history' => ['inventory', 'View Stock History'],
            'customers.view' => ['customers', 'View Customers'], 'customers.create' => ['customers', 'Create Customers'], 'customers.edit' => ['customers', 'Edit Customers'], 'customers.archive' => ['customers', 'Archive Customers'],
            'suppliers.view' => ['suppliers', 'View Suppliers'], 'suppliers.create' => ['suppliers', 'Create Suppliers'], 'suppliers.edit' => ['suppliers', 'Edit Suppliers'], 'suppliers.archive' => ['suppliers', 'Archive Suppliers'],
            'orders.view' => ['orders', 'View Orders'], 'orders.create' => ['orders', 'Create Orders'], 'orders.edit' => ['orders', 'Edit Orders'], 'orders.update_status' => ['orders', 'Update Order Status'], 'orders.cancel' => ['orders', 'Cancel Orders'], 'orders.delete' => ['orders', 'Delete Orders'], 'orders.assign_delivery' => ['orders', 'Assign Delivery'],
            'payments.view' => ['payments', 'View Payments'], 'payments.create' => ['payments', 'Record Payments'], 'payments.verify' => ['payments', 'Verify Payments'], 'payments.reverse' => ['payments', 'Reverse Payments'],
            'accounting.view' => ['accounting', 'View Accounting'], 'accounting.create_journal' => ['accounting', 'Create Journal Entries'], 'accounting.post_journal' => ['accounting', 'Post Journal Entries'], 'accounting.export' => ['accounting', 'Export Accounting'],
            'deliveries.view' => ['deliveries', 'View Deliveries'], 'deliveries.assign' => ['deliveries', 'Assign Deliveries'], 'deliveries.edit' => ['deliveries', 'Edit Deliveries'], 'deliveries.update_status' => ['deliveries', 'Update Delivery Status'], 'deliveries.upload_proof' => ['deliveries', 'Upload Delivery Proof'],
            'invoices.view' => ['invoices', 'View Invoices'], 'invoices.create' => ['invoices', 'Create Invoices'], 'invoices.print' => ['invoices', 'Print Invoices'], 'invoices.export' => ['invoices', 'Export Invoices'], 'invoices.void' => ['invoices', 'Void Invoices'],
            'expenses.view' => ['expenses', 'View Expenses'], 'expenses.create' => ['expenses', 'Create Expenses'],
            'reports.view' => ['reports', 'View Reports'], 'reports.export' => ['reports', 'Export Reports'],
            'staff.view' => ['staff', 'View Staff'], 'staff.create' => ['staff', 'Create Staff'], 'staff.edit' => ['staff', 'Edit Staff'], 'staff.permissions' => ['staff', 'Manage Staff Permissions'],
            'settings.view' => ['settings', 'View Settings'], 'settings.update' => ['settings', 'Update Settings'],
        ];

        foreach ($definitions as $key => [$module, $label]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => $module, 'label' => $label, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_approval_histories');
        Schema::dropIfExists('permission_template_items');
        Schema::dropIfExists('permission_templates');
        Schema::dropIfExists('company_permissions');
        Schema::dropIfExists('permission_definitions');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['archived_at', 'archived_status']);
        });
    }
};
