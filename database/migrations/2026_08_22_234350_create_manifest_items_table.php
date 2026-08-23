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
        Schema::create('manifest_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('manifest_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('handling_unit_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('loaded_by')
                ->constrained('users');
            $table->uuid('client_event_id')->unique();
            $table->timestamp('loaded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_items');
    }
};
