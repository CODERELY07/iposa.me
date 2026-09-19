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
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('counted_by');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
