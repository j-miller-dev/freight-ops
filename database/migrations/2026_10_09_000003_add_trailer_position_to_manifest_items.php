<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('manifest_items', function (Blueprint $table) {
            // Where a pallet sits on the trailer: unit (1 = lead), row from the
            // front, and side (D = driver, P = passenger). Recorded for DG.
            $table->unsignedTinyInteger('trailer_unit')->nullable();
            $table->unsignedTinyInteger('trailer_row')->nullable();
            $table->string('trailer_side', 1)->nullable();

            // One pallet per position; rows without a position are unconstrained.
            $table->unique(
                ['manifest_id', 'trailer_unit', 'trailer_row', 'trailer_side'],
                'manifest_items_position_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_items', function (Blueprint $table) {
            // The manifest_id foreign key leans on the unique index, so it needs
            // a plain index of its own before that one can go.
            $table->index('manifest_id', 'manifest_items_manifest_id_index');
            $table->dropUnique('manifest_items_position_unique');
            $table->dropColumn(['trailer_unit', 'trailer_row', 'trailer_side']);
        });
    }
};
