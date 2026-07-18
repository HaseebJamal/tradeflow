<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('suppliers')->whereNull('opening_balance')->update(['opening_balance' => 0]);

        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->change();
        });

        Schema::create('document_number_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 50);
            $table->date('number_date');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['scope', 'number_date']);
        });

        if (!Schema::hasColumn('pos_returns', 'return_number')) {
            Schema::table('pos_returns', function (Blueprint $table) {
                $table->string('return_number')->nullable()->after('id');
            });
        }

        foreach (DB::table('products')->select('id', 'business_id', 'barcode')->orderBy('id')->get() as $product) {
            $generated = str_pad((string) $product->business_id, 3, '0', STR_PAD_LEFT)
                .str_pad((string) $product->id, 9, '0', STR_PAD_LEFT);
            $duplicate = filled($product->barcode) && DB::table('products')
                ->where('business_id', $product->business_id)
                ->where('barcode', $product->barcode)
                ->where('id', '<', $product->id)
                ->exists();

            if (blank($product->barcode) || $duplicate) {
                $attempt = 0;
                while (DB::table('products')
                    ->where('business_id', $product->business_id)
                    ->where('barcode', $generated)
                    ->where('id', '!=', $product->id)
                    ->exists()) {
                    $attempt++;
                    $generated = substr(str_pad((string) $product->business_id, 3, '0', STR_PAD_LEFT)
                        .str_pad((string) $product->id, 9, '0', STR_PAD_LEFT), 0, 10)
                        .str_pad((string) $attempt, 2, '0', STR_PAD_LEFT);
                }
                DB::table('products')->where('id', $product->id)->update(['barcode' => $generated, 'updated_at' => now()]);
            }
        }

        foreach (DB::table('pos_returns')->select('id', 'returned_at', 'created_at', 'return_number')->orderBy('id')->get() as $return) {
            $duplicate = filled($return->return_number) && DB::table('pos_returns')
                ->where('return_number', $return->return_number)
                ->where('id', '<', $return->id)
                ->exists();
            if (blank($return->return_number) || $duplicate) {
                $date = \Illuminate\Support\Carbon::parse($return->returned_at ?? $return->created_at ?? now())->format('Ymd');
                DB::table('pos_returns')->where('id', $return->id)->update([
                    'return_number' => 'SR-'.$date.'-'.str_pad((string) $return->id, 6, '0', STR_PAD_LEFT),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['business_id', 'barcode'], 'products_business_barcode_unique');
        });
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->unique('return_number');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_business_barcode_unique');
        });
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->dropUnique(['return_number']);
        });
        Schema::dropIfExists('document_number_counters');
    }
};
