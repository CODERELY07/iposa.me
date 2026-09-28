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
        Schema::table('items', function (Blueprint $table) {
            // Whether a closing audit's extra usage (beyond what recipes explain) counts
            // against profit for this item. On by default: matches how every item behaves today.
            $table->boolean('include_audit_cost')->default(true)->after('include_recipe_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('include_audit_cost');
        });
    }
};
