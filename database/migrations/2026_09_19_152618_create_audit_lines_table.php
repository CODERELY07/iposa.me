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
        Schema::create('audit_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('expected', 12, 3);
            $table->decimal('counted', 12, 3);
            $table->decimal('used', 12, 3);
            $table->decimal('restocked', 12, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();

            $table->unique(['audit_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_lines');
    }
};
