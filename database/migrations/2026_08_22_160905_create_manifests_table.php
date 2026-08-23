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
        Schema::create('manifests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('depot_id')->constrained();
            $table->string('source');
            $table->string('external_id');
            $table->string('manifest_number');
            $table->date('service_date');
            $table->string('status')->default('open');
            $table->string('trailer_label')->nullable();
            $table->string('trailer_registration')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });

        Schema::create('manifest_destinations', function (Blueprint $table) {
            $table->foreignUuid('manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('destination_depot_id')
                ->constrained('depots')
                ->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);

            $table->primary(['manifest_id', 'destination_depot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_destinations');
        Schema::dropIfExists('manifests');
    }
};
