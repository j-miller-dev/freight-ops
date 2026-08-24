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
        Schema::create('warning_acknowledgements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('warning_type');

            $table->foreignUuid('handling_unit_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('manifest_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('conflicting_manifest_id')
                ->nullable()
                ->constrained('manifests')
                ->nullOnDelete();

            $table->foreignId('acknowledged_by')
                ->constrained('users');

            $table->uuid('client_event_id')->index();
            $table->timestamp('acknowledged_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warning_acknowledgements');
    }
};
