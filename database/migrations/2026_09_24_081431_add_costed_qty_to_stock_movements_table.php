<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The part of a sale's deduction whose cost the sale already charged (its
     * menu item has "Include linked pieces & liquids in cost" on). Voids reverse
     * it. The closing audit uses it to give back exactly the cost of what the
     * recipes over-deducted, and nothing that was never charged.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('costed_qty', 12, 3)->default(0)->after('qty_change');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('costed_qty');
        });
    }
};
