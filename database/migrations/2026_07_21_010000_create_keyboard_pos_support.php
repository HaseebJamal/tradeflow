<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_registers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('Open');
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->string('opening_note')->nullable();
            $table->timestamp('opened_at');
            $table->unsignedBigInteger('closing_cash')->nullable();
            $table->unsignedBigInteger('expected_cash')->nullable();
            $table->integer('variance')->nullable();
            $table->string('closing_note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'user_id', 'status']);
        });

        Schema::create('held_pos_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_register_id')->constrained('pos_registers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('hold_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->json('cart_payload');
            $table->json('checkout_payload')->nullable();
            $table->string('status')->default('Held');
            $table->timestamp('held_at');
            $table->timestamp('resumed_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'held_at']);
        });

        $definitions = [
            ['pos', 'pos.view', 'module', 'Enable POS'],
            ['pos', 'pos.open_register', 'action', 'Open POS Register'],
            ['pos', 'pos.close_register', 'action', 'Close POS Register'],
            ['pos', 'pos.create_sale', 'action', 'Create POS Sale'],
            ['pos', 'pos.hold_sale', 'action', 'Hold POS Sale'],
            ['pos', 'pos.resume_sale', 'action', 'Resume POS Sale'],
            ['pos', 'pos.view_history', 'action', 'View POS History'],
            ['pos', 'pos.print_receipt', 'action', 'Print POS Receipt'],
            ['pos', 'pos.apply_discount', 'action', 'Apply POS Discount'],
            ['pos', 'pos.custom_price', 'action', 'Use Custom POS Price'],
            ['pos', 'pos.credit_sale', 'action', 'Create POS Credit Sale'],
            ['pos', 'pos.split_payment', 'action', 'Use POS Split Payment'],
            ['pos', 'pos.process_return', 'action', 'Process POS Return'],
        ];

        foreach ($definitions as [$module, $key, $type, $label]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => $module, 'permission_type' => $type, 'label' => $label, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('held_pos_sales');
        Schema::dropIfExists('pos_registers');
        DB::table('permission_definitions')->where('module', 'pos')->delete();
        DB::table('company_permissions')->where('permission_key', 'like', 'pos.%')->delete();
    }
};
