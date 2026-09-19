<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterBusinessUserService
{
    /**
     * Create a new class instance.
     */
    public function register(array $data){
        return DB::transaction(function () use ($data){
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->business()->create([
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
            ]);

            return $user;
        });
    }
}
