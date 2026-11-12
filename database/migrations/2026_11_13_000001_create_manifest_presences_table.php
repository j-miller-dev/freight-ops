<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who has a manifest open right now. One row per person per manifest,
     * touched by a heartbeat; a row is "present" while it is recent.
     */
    public function up(): void
    {
        Schema::create('manifest_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['manifest_id', 'user_id']);
            $table->index(['manifest_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest_presences');
    }
};
