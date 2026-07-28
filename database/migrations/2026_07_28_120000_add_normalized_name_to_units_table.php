<?php

use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('units', 'unit_name_normalized')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->string('unit_name_normalized', 100)->nullable()->after('unit_name');
            });
        }

        // Preserve legacy records even if historical names collide after
        // normalization. New records are protected by the unique index.
        $seen = [];
        DB::table('units')->orderBy('id')->select(['id', 'business_id', 'unit_name'])->each(function (object $unit) use (&$seen): void {
            $normalised = strtolower(Unit::normalizeName((string) $unit->unit_name));
            $key = $unit->business_id.'|'.$normalised;

            DB::table('units')->where('id', $unit->id)->update([
                'unit_name_normalized' => isset($seen[$key]) ? null : $normalised,
            ]);
            $seen[$key] = true;
        });

        $hasIndex = collect(Schema::getIndexes('units'))
            ->contains(fn (array $index): bool => $index['name'] === 'units_business_id_unit_name_normalized_unique');

        if (! $hasIndex) {
            Schema::table('units', function (Blueprint $table): void {
                $table->unique(['business_id', 'unit_name_normalized'], 'units_business_id_unit_name_normalized_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('units', 'unit_name_normalized')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->dropUnique('units_business_id_unit_name_normalized_unique');
                $table->dropColumn('unit_name_normalized');
            });
        }
    }
};
