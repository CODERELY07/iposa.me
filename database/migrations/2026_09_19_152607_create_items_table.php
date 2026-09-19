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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->string('unit')->nullable();
            $table->decimal('on_hand', 12, 3)->nullable();
            $table->decimal('low_threshold', 12, 3)->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'kind', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
