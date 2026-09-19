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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->uuid('uuid');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cashier_name');
            $table->string('payment_method');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tendered', 12, 2)->nullable();
            $table->decimal('change', 12, 2)->nullable();
            $table->string('status')->default('paid');
            $table->timestamp('paid_at');
            $table->foreignId('void_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number']);
            $table->unique(['business_id', 'uuid']);
            $table->index(['business_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
