<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An id the closing audit makes up when the count is saved offline, so replaying it after a
     * dropped connection never closes the day twice. Empty for counts submitted online.
     */
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('business_id');
            $table->unique(['business_id', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'uuid']);
            $table->dropColumn('uuid');
        });
    }
};
