<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->string('pitch')->nullable();
            $table->unsignedSmallInteger('staff_limit')->nullable();
            $table->json('features');
            $table->json('feature_list');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        $this->seedFromConfig();
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }

    /**
     * The plans that used to live in config/plans.php become the first rows,
     * so existing businesses keep the plan they are already on.
     */
    private function seedFromConfig(): void
    {
        $sort = 0;
        $rows = [];

        foreach ((array) config('plans.plans') as $key => $plan) {
            $rows[] = [
                'key' => $key,
                'name' => $plan['name'],
                'price' => $plan['price'],
                'pitch' => $plan['pitch'] ?? null,
                'staff_limit' => $plan['staff_limit'] ?? null,
                'features' => json_encode($plan['features'] ?? []),
                'feature_list' => json_encode($plan['feature_list'] ?? []),
                'sort' => $sort += 10,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('plans')->insert($rows);
        }
    }
};
