<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A menu item that counts itself (bottled water) can keep a separate count per
     * size. Purely additive: NULL on_hand on a size means "not counted per size",
     * so every existing item keeps its single shared count exactly as it was.
     */
    public function up(): void
    {
        Schema::table('item_variants', function (Blueprint $table) {
            $table->decimal('on_hand', 12, 3)->nullable()->after('price');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('item_variant_id')->nullable()->after('item_id')->constrained('item_variants')->nullOnDelete();
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('item_variant_id')->nullable()->after('item_id')->constrained('item_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_variant_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_variant_id');
        });

        Schema::table('item_variants', function (Blueprint $table) {
            $table->dropColumn('on_hand');
        });
    }
};
