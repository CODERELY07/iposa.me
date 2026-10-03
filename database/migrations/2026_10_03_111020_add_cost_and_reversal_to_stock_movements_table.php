<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waste is costed when it is logged: unit_cost is what one unit was worth that day
     * (NULL when the item had no cost set, never a silent 0), so a later price change can't
     * rewrite it. reverses_id points an undo at the waste entry it cancels.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 6)->nullable()->after('costed_qty');
            $table->foreignId('reverses_id')->nullable()->after('item_variant_id')->constrained('stock_movements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_id');
            $table->dropColumn('unit_cost');
        });
    }
};
