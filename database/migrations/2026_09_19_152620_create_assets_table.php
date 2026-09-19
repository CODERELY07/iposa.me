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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vendor')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('installment_amount', 12, 2)->nullable();
            $table->unsignedSmallInteger('terms')->default(1);
            $table->unsignedSmallInteger('paid_count')->default(0);
            $table->date('first_due_on');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
