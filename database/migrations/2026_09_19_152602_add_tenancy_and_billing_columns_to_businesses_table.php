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
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('plan')->default('negosyo')->after('business_type');
            $table->string('address')->nullable()->after('plan');
            $table->string('tin')->nullable()->after('address');
            $table->string('receipt_footer')->nullable()->after('tin');
            $table->json('settings')->nullable()->after('receipt_footer');
            $table->unsignedInteger('last_order_number')->default(0)->after('settings');
            $table->timestamp('suspended_at')->nullable()->after('due_date');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['plan', 'address', 'tin', 'receipt_footer', 'settings', 'last_order_number', 'suspended_at', 'suspension_reason']);
        });
    }
};
