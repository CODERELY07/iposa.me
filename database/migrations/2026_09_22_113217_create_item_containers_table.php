<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How an item is bought: "1 bottle holds 1000 ml, ₱145 each".
     *
     * Stock is still stored in the item's own unit (ml, g); containers only
     * describe how it is bought and counted. An item can have several sizes
     * (a 1 L bottle and an 18 L tin of the same oil). An item with no
     * containers works exactly as before.
     */
    public function up(): void
    {
        Schema::create('item_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->decimal('size', 14, 3);
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['item_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_containers');
    }
};
