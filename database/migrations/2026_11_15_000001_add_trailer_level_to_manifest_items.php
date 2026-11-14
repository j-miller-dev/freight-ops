<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('manifest_items', function (Blueprint $table) {
            // 1 = on the floor (bottom), 2 = stacked on top. Freight is
            // sometimes double stacked, so a spot holds up to two pallets.
            $table->unsignedTinyInteger('trailer_level')->nullable()->after('trailer_side');
        });

        DB::table('manifest_items')
            ->whereNotNull('trailer_unit')
            ->update(['trailer_level' => 1]);

        Schema::table('manifest_items', function (Blueprint $table) {
            // The manifest_id foreign key leans on the unique index, so it needs
            // a plain index of its own before that one can go.
            $table->index('manifest_id', 'manifest_items_manifest_id_index');
            $table->dropUnique('manifest_items_position_unique');
            $table->unique(
                ['manifest_id', 'trailer_unit', 'trailer_row', 'trailer_side', 'trailer_level'],
                'manifest_items_position_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Two pallets can share a spot now; the old index would refuse them.
        DB::table('manifest_items')->where('trailer_level', '>', 1)->update([
            'trailer_unit' => null,
            'trailer_row' => null,
            'trailer_side' => null,
        ]);

        Schema::table('manifest_items', function (Blueprint $table) {
            $table->dropUnique('manifest_items_position_unique');
            $table->unique(
                ['manifest_id', 'trailer_unit', 'trailer_row', 'trailer_side'],
                'manifest_items_position_unique',
            );
            $table->dropIndex('manifest_items_manifest_id_index');
            $table->dropColumn('trailer_level');
        });
    }
};
