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
        Schema::create('trailers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Fleet name shown to loaders, e.g. "F-Ops 2" or a contractor's name.
            $table->string('name')->unique();
            $table->string('owner')->default('own');
            $table->string('operator_name')->nullable();
            $table->string('registration')->nullable();
            $table->string('default_type')->default('b_double');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('manifests', function (Blueprint $table) {
            $table->foreignUuid('trailer_id')->nullable()->after('depot_id')
                ->constrained('trailers')->nullOnDelete();
            // The loader's "I'm done" signal; the scaler closes and dispatches upstream.
            $table->timestamp('loading_finished_at')->nullable();
            $table->foreignId('loading_finished_by')->nullable()
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('manifest_equipment', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('manifest_id')->constrained()->cascadeOnDelete();
            $table->string('item');
            $table->unsignedSmallInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['manifest_id', 'item']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('operator')->after('pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::dropIfExists('manifest_equipment');

        Schema::table('manifests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loading_finished_by');
            $table->dropColumn('loading_finished_at');
            $table->dropConstrainedForeignId('trailer_id');
        });

        Schema::dropIfExists('trailers');
    }
};
