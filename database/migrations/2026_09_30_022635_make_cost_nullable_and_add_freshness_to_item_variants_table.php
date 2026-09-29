<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NULL now means "cost not configured", distinct from a genuine ₱0. Existing
     * rows keep their exact 0.00 as a configured value -- nothing is reinterpreted
     * as unknown retroactively. cost_updated_at powers the manual-cost freshness
     * warning and is only ever set when the owner actually changes the cost.
     */
    public function up(): void
    {
        Schema::table('item_variants', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->nullable()->default(null)->change();
            $table->timestamp('cost_updated_at')->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('item_variants', function (Blueprint $table) {
            $table->dropColumn('cost_updated_at');
            $table->decimal('cost', 12, 2)->default(0)->change();
        });
    }
};
