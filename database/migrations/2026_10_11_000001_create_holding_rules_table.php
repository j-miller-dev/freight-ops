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
        Schema::create('holding_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Rules are by sender (customer), not by size alone.
            $table->string('sender_name')->unique();
            $table->unsignedTinyInteger('max_hold_days')->default(3);
            $table->string('reason');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holding_rules');
    }
};
