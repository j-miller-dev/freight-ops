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
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('depot_id')->constrained();
            $table->string('code');
            $table->string('name');
            $table->string('type');
            // The depot whose freight this bay collects, e.g. the SYD02 bay in MEL.
            $table->foreignUuid('destination_depot_id')->nullable()->constrained('depots');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['depot_id', 'code']);
        });

        Schema::table('handling_units', function (Blueprint $table) {
            $table->foreignUuid('current_location_id')
                ->nullable()
                ->after('current_status')
                ->constrained('locations')
                ->nullOnDelete();
            $table->string('dg_class')->nullable()->after('weight_kg');
            $table->string('un_number')->nullable()->after('dg_class');
            $table->string('proper_shipping_name')->nullable()->after('un_number');
            $table->boolean('is_food')->default(false)->after('proper_shipping_name');
        });

        Schema::table('manifests', function (Blueprint $table) {
            $table->timestamp('departs_at')->nullable()->after('service_date');
            $table->string('trailer_type')->default('b_double')->after('trailer_registration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            $table->dropColumn(['departs_at', 'trailer_type']);
        });

        Schema::table('handling_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_location_id');
            $table->dropColumn(['dg_class', 'un_number', 'proper_shipping_name', 'is_food']);
        });

        Schema::dropIfExists('locations');
    }
};
