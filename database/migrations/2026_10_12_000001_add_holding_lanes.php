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
        Schema::table('locations', function (Blueprint $table) {
            // Holding lanes are numbered 1 (leaves soonest) to 5 (can wait longest).
            $table->unsignedTinyInteger('lane')->nullable()->after('type');
        });

        Schema::table('holding_rules', function (Blueprint $table) {
            // The lane a standard-service pallet from this sender goes to; more
            // urgent services shift it toward lane 1.
            $table->unsignedTinyInteger('base_lane')->default(3)->after('max_hold_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holding_rules', function (Blueprint $table) {
            $table->dropColumn('base_lane');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('lane');
        });
    }
};
