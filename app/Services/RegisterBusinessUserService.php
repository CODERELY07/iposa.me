<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterBusinessUserService
{
    /**
     * Length of the free trial every new business starts with.
     */
    public const TRIAL_DAYS = 14;

    /**
     * Create the owner account and their business in one transaction.
     *
     * @param  array{name: string, email: string, password: string, business_name: string, business_type: string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill(['role' => User::ROLE_ADMIN])->save();

            $business = Business::create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'plan' => config('plans.default'),
                'status' => BusinessStatus::Trial,
                'start_date' => now(),
                'due_date' => now()->addDays(self::TRIAL_DAYS),
            ]);

            $user->forceFill(['business_id' => $business->id])->save();

            return $user;
        });
    }
}
