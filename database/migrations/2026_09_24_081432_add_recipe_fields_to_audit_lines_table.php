<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * recipe_deducted: what sales deducted through recipes since the item's previous count,
     * and recipe_deducted_costed the part of it whose cost those sales charged.
     * recipe_surplus_costed: the part of recipe_surplus whose cost sales had charged, given
     * back in the profit report. recipe_fix: what the owner did about it (applied, dismissed).
     */
    public function up(): void
    {
        Schema::table('audit_lines', function (Blueprint $table) {
            $table->decimal('recipe_deducted', 12, 3)->default(0)->after('recipe_surplus');
            $table->decimal('recipe_deducted_costed', 12, 3)->default(0)->after('recipe_deducted');
            $table->decimal('recipe_surplus_costed', 12, 3)->default(0)->after('recipe_deducted_costed');
            $table->string('recipe_fix')->nullable()->after('recipe_surplus_costed');
            $table->timestamp('recipe_fix_at')->nullable()->after('recipe_fix');
        });

        // Where the count happened in the stock history, so the next count knows which
        // sales came after it, even for items whose count matched and moved nothing.
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedBigInteger('last_movement_id')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('audit_lines', function (Blueprint $table) {
            $table->dropColumn(['recipe_deducted', 'recipe_deducted_costed', 'recipe_surplus_costed', 'recipe_fix', 'recipe_fix_at']);
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('last_movement_id');
        });
    }
};
