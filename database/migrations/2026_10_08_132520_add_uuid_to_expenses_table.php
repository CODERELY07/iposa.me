<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An id the register makes up for an expense logged offline, so replaying it after a
     * dropped connection never records it twice. Empty for expenses typed in directly.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('business_id');
            $table->unique(['business_id', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'uuid']);
            $table->dropColumn('uuid');
        });
    }
};
