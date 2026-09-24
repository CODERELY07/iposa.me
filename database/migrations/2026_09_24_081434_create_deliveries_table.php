<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A restock recorded by a cashier, waiting for the owner to check it against
     * the supplier's receipt. `added` is what the cashier put on the shelf in the
     * item's own unit; `receipt_added` is what the receipt says was bought.
     */
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('received_by');
            $table->decimal('quantity', 12, 3);
            $table->foreignId('item_container_id')->nullable()->constrained()->nullOnDelete();
            $table->string('container_label')->nullable();
            $table->decimal('container_size', 12, 3)->nullable();
            $table->decimal('added', 14, 3);
            $table->string('status')->default('pending');
            $table->decimal('receipt_added', 14, 3)->nullable();
            $table->decimal('paid', 12, 2)->nullable();
            $table->decimal('missing_cost', 12, 2)->default(0);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checked_by_name')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
