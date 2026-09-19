<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Creates (or promotes) the platform operator account. Safe to run on every deploy:
 * an existing account keeps its password unless --reset-password is passed.
 */
#[Signature('app:ensure-super-admin
    {--email= : Email of the operator (default: SUPER_ADMIN_EMAIL)}
    {--name= : Display name (default: SUPER_ADMIN_NAME or "Platform Operator")}
    {--password= : Password for a new account (default: SUPER_ADMIN_PASSWORD)}
    {--reset-password : Also set the password on an existing account}')]
#[Description('Create or promote the iPOSa platform operator (super_admin)')]
class EnsureSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: config('iposa.super_admin.email'));
        $name = (string) ($this->option('name') ?: config('iposa.super_admin.name'));
        $password = (string) ($this->option('password') ?: config('iposa.super_admin.password'));

        if ($email === '') {
            $this->warn('No operator email given (set SUPER_ADMIN_EMAIL). Skipped.');

            return self::SUCCESS;
        }

        $user = User::firstOrNew(['email' => strtolower($email)]);
        $isNew = ! $user->exists;

        if (($isNew || $this->option('reset-password')) && strlen($password) < 12) {
            $this->error('The operator password must be at least 12 characters (set SUPER_ADMIN_PASSWORD).');

            return self::FAILURE;
        }

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $attributes = [
            'name' => $isNew ? $name : $user->name,
            'role' => User::ROLE_SUPER_ADMIN,
            'business_id' => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ];

        if ($isNew || $this->option('reset-password')) {
            $attributes['password'] = Hash::make($password);
        }

        $user->forceFill($attributes)->save();

        $this->info($isNew ? "Operator {$user->email} created." : "Operator {$user->email} is up to date.");

        return self::SUCCESS;
    }
}
