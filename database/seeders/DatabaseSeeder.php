<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo accounts, one fully used demo shop, and a platform full of other shops.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DemoShopSeeder::class,
            BusinessSeeder::class,
        ]);
    }
}
