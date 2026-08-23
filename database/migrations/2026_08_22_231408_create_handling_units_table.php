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
        Schema::create('handling_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consignment_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('barcode')->unique();
            $table->unsignedInteger('piece_number');
            $table->string('current_status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handling_units');
    }
};
