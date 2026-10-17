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
        Schema::table('manifests', function (Blueprint $table) {
            // Who closed/dispatched it locally, when that happens in Freight Ops
            // itself rather than only being mirrored from an upstream sync.
            $table->foreignId('closed_by')->nullable()->after('closed_at')
                ->constrained('users')->nullOnDelete();

            // Snapshot of reconciliation state at the moment of closing, so an
            // override (closed with pallets still outstanding) stays auditable
            // even after the live counts move on.
            $table->json('close_summary')->nullable()->after('closed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            $table->dropColumn('close_summary');
            $table->dropConstrainedForeignId('closed_by');
        });
    }
};
