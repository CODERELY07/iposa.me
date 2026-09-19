<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts (password: "password"). Verified, so they can open the app right away.
     * The owner and cashier are linked to their shop in BusinessSeeder.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Maria Santos', 'email' => 'admin@gmail.com', 'role' => User::ROLE_ADMIN],
            ['name' => 'Platform Operator', 'email' => 'calipjo.markely@gmail.com', 'role' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Jessa Reyes', 'email' => 'staff@gmail.com', 'role' => User::ROLE_STAFF],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrNew(['email' => $account['email']]);
            $user->forceFill([
                'name' => $account['name'],
                'password' => Hash::make('password'),
                'role' => $account['role'],
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
