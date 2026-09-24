<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every change to what one sale uses: the owner's own saves, and cashier
     * requests that wait for the owner's approval. `before` and `after` keep
     * names and units as they were, so the history reads right after renames.
     */
    public function up(): void
    {
        Schema::create('recipe_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requested_by');
            $table->string('status');
            $table->json('before');
            $table->json('after');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_changes');
    }
};
