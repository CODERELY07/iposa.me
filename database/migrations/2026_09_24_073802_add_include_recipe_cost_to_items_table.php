<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When on, a menu item's cost per sale is its own cost (labor, packaging the
     * owner typed) plus what its linked pieces and liquids cost at today's prices.
     * Existing items get false, so their costs stay exactly what the owner typed.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('include_recipe_cost')->default(false)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('include_recipe_cost');
        });
    }
};
