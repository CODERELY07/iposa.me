<?php

namespace App\Services\Team;

use App\Models\Business;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    /**
     * Add a cashier and email them a "set your password" link.
     * The invite link proves the email, so the account starts verified.
     *
     * @param  array{name: string, email: string}  $data
     *
     * @throws ValidationException
     */
    public function invite(Business $business, array $data): User
    {
        if ($business->staffSeatsLeft() === 0) {
            throw ValidationException::withMessages([
                'email' => "Your {$business->planDetails()['name']} plan includes {$business->planDetails()['staff_limit']} staff. Upgrade to Negosyo for unlimited staff.",
            ]);
        }

        $user = DB::transaction(function () use ($business, $data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(40)),
            ]);

            $user->forceFill([
                'role' => User::ROLE_STAFF,
                'business_id' => $business->id,
                'email_verified_at' => now(),
            ])->save();

            return $user;
        });

        $user->notify(new StaffInvitation($business, Password::broker()->createToken($user)));

        return $user;
    }

    public function resendInvite(Business $business, User $staff): void
    {
        $staff->notify(new StaffInvitation($business, Password::broker()->createToken($staff)));
    }

    /**
     * Remove a cashier. Their past orders keep their name; their sessions end now.
     *
     * @throws ValidationException
     */
    public function remove(Business $business, User $staff): void
    {
        if ($staff->business_id !== $business->id || ! $staff->isStaff()) {
            throw ValidationException::withMessages(['user' => 'Only cashiers of this business can be removed.']);
        }

        DB::table('sessions')->where('user_id', $staff->id)->delete();

        $staff->delete();
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    public function updateCashierPermissions(Business $business, array $permissions): Business
    {
        $settings = $business->settings ?? [];
        $settings['cashier_permissions'] = array_intersect_key(
            array_map(fn ($value) => (bool) $value, $permissions),
            Business::DEFAULT_SETTINGS['cashier_permissions'],
        ) + ($settings['cashier_permissions'] ?? []);

        $business->update(['settings' => $settings]);

        return $business;
    }
}
