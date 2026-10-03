<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who sold it and at what price, every time an item with a price is bought. This is a
     * record only: it never feeds a sale's locked-in cost or any profit figure.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
        });

        Schema::create('item_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_cost', 14, 6);
            $table->decimal('quantity', 12, 3);
            $table->decimal('paid', 12, 2);
            $table->string('source', 16);
            $table->date('bought_on');
            $table->timestamps();

            $table->index(['item_id', 'bought_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_purchases');
        Schema::dropIfExists('suppliers');
    }
};
