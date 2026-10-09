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
            $table->timestamp('held_back_at')->nullable()->after('service_code');
            $table->foreignId('held_back_by')
                ->nullable()
                ->after('held_back_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('held_back_reason')->nullable()->after('held_back_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_back_by');
            $table->dropColumn(['held_back_at', 'held_back_reason']);
        });
    }
};
