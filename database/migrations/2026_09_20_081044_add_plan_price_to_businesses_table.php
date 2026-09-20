<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price a business agreed to, locked in when they sign up, switch plans or renew.
     * Raising a plan's price therefore never changes what an existing shop owes
     * until their next renewal.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('plan_price', 12, 2)->nullable()->after('plan');
        });

        DB::table('businesses')->whereNull('plan_price')->update([
            'plan_price' => DB::raw('(select price from plans where plans.key = businesses.plan)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('plan_price');
        });
    }
};
