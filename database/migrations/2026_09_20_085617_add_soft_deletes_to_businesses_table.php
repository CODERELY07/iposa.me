<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removing a shop is a two-step action for the operator: it goes to the trash
     * first, where it can be restored, and is only erased from there on purpose.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('deletion_reason')->nullable()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'deletion_reason']);
        });
    }
};
