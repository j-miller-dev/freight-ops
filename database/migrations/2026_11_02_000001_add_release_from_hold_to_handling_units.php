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
        Schema::table('handling_units', function (Blueprint $table) {
            // A supervisor's "move this one now" override on a specific held
            // pallet. Scoped to this physical pallet only -- it does not change
            // the sender's holding rule for anything else.
            $table->timestamp('released_from_hold_at')->nullable();
            $table->foreignId('released_from_hold_by')->nullable()
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handling_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_from_hold_by');
            $table->dropColumn('released_from_hold_at');
        });
    }
};
