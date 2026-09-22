<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cost per unit keeps six decimals, so small units price correctly:
     * a ₱145 1-litre bottle is ₱0.145 per ml, which two decimals stored as ₱0.15,
     * and a ₱45 3.785-litre jug is ₱0.011889 per ml, which became ₱0.01.
     *
     * Widening only: every existing value fits unchanged. Peso totals are still
     * rounded to the centavo where they are shown and reported.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 6)->nullable()->change();
        });

        Schema::table('audit_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 6)->change();
        });
    }

    /**
     * Narrowing back rounds any cost with more than two decimals to the centavo.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->change();
        });

        Schema::table('audit_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->change();
        });
    }
};
