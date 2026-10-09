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
        Schema::create('consignment_hold_back_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('consignment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('actor_id')->constrained('users');
            $table->boolean('held_back');
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consignment_hold_back_events');
    }
};
