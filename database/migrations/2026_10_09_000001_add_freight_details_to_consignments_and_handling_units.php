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
        Schema::table('consignments', function (Blueprint $table) {
            $table->string('sender_name')->nullable()->after('destination_depot_id');
            $table->string('receiver_name')->nullable()->after('sender_name');
            $table->string('service_code')->nullable()->after('receiver_name');
        });

        Schema::table('handling_units', function (Blueprint $table) {
            $table->unsignedInteger('weight_kg')->nullable()->after('piece_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            $table->dropColumn(['sender_name', 'receiver_name', 'service_code']);
        });

        Schema::table('handling_units', function (Blueprint $table) {
            $table->dropColumn('weight_kg');
        });
    }
};
