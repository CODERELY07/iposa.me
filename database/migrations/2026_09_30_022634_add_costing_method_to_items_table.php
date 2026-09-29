<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the include_recipe_cost boolean with an explicit costing method,
     * so "manual cost only" and "manual + linked" are both first-class choices.
     * Existing data keeps its exact meaning: false -> manual_only, true -> manual_plus_linked.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('costing_method')->default('manual_only')->after('include_recipe_cost');
        });

        DB::table('items')->where('include_recipe_cost', true)->update(['costing_method' => 'manual_plus_linked']);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('include_recipe_cost');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('include_recipe_cost')->default(false)->after('unit_cost');
        });

        DB::table('items')->where('costing_method', 'manual_plus_linked')->update(['include_recipe_cost' => true]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('costing_method');
        });
    }
};
