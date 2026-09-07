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
        Schema::create('operational_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_event_id')->unique();
            $table->foreignUuid('handling_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event_type');
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_events');
    }
};
