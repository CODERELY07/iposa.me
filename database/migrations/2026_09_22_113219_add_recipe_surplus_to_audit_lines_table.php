<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a liquid used in recipes counts higher than the system expects, it is
     * either a restock (`restocked`, as before) or the recipes deduct more than
     * the kitchen really uses. The second case goes here, so the owner can be
     * told "burgers use less ketchup than the recipe says".
     *
     * Existing rows get 0, which is what they already meant.
     */
    public function up(): void
    {
        Schema::table('audit_lines', function (Blueprint $table) {
            $table->decimal('recipe_surplus', 12, 3)->default(0)->after('restocked');
        });
    }

    public function down(): void
    {
        Schema::table('audit_lines', function (Blueprint $table) {
            $table->dropColumn('recipe_surplus');
        });
    }
};
