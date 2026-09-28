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
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedInteger('corrections_count')->default(0)->after('duration_seconds');

            // A cashier asking the owner to reopen tonight's count: null once nothing is pending
            // (also cleared the moment an approval is used for a correction, so asking again needs asking again).
            $table->string('reopen_status')->nullable()->after('corrections_count');
            $table->foreignId('reopen_requested_by')->nullable()->after('reopen_status')->constrained('users')->nullOnDelete();
            $table->string('reopen_requested_by_name')->nullable()->after('reopen_requested_by');
            $table->timestamp('reopen_requested_at')->nullable()->after('reopen_requested_by_name');
            $table->foreignId('reopen_decided_by')->nullable()->after('reopen_requested_at')->constrained('users')->nullOnDelete();
            $table->string('reopen_decided_by_name')->nullable()->after('reopen_decided_by');
            $table->timestamp('reopen_decided_at')->nullable()->after('reopen_decided_by_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopen_requested_by');
            $table->dropConstrainedForeignId('reopen_decided_by');
            $table->dropColumn([
                'corrections_count',
                'reopen_status',
                'reopen_requested_by_name',
                'reopen_requested_at',
                'reopen_decided_by_name',
                'reopen_decided_at',
            ]);
        });
    }
};
