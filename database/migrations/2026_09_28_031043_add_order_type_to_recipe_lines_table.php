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
        Schema::table('recipe_lines', function (Blueprint $table) {
            // Null applies to both dine-in and take-out, same as a null item_variant_id applying to every size.
            $table->string('order_type')->nullable()->after('item_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipe_lines', function (Blueprint $table) {
            $table->dropColumn('order_type');
        });
    }
};
