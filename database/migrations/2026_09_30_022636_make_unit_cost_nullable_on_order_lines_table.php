<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sale can lock in "cost unknown" now, so it must be storable as NULL rather
     * than silently rounding down to 0 and inflating that sale's reported profit.
     */
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->default(0)->change();
        });
    }
};
