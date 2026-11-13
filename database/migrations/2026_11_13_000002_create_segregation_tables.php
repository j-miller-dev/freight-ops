<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "These two kinds of freight must not share a trailer unit." Kept as
        // data so a new rule is a row, not a deploy.
        Schema::create('load_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category_a');
            $table->string('category_b');
            $table->string('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_a', 'category_b']);
        });

        // What a loader has said a trailer unit (1 = A, 2 = B, 3 = C) is carrying.
        Schema::create('manifest_unit_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('manifest_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('unit');
            $table->string('category');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['manifest_id', 'unit', 'category']);
        });

        // The operation's working rules. They still need validating against the
        // ADG Code segregation tables before being relied on for compliance.
        $now = now();

        DB::table('load_rules')->insert(array_map(
            fn (array $rule): array => $rule + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            [
                ['category_a' => 'food', 'category_b' => '6.1', 'reason' => 'Toxic goods must not travel with food'],
                ['category_a' => 'food', 'category_b' => '8', 'reason' => 'Corrosives must not travel with food'],
                ['category_a' => '4.1', 'category_b' => 'flammable', 'reason' => 'Class 4.1 must not travel with flammables'],
                ['category_a' => '4.1', 'category_b' => 'oil', 'reason' => 'Class 4.1 must not travel with oil'],
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest_unit_labels');
        Schema::dropIfExists('load_rules');
    }
};
