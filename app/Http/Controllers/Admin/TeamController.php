<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteStaffRequest;
use App\Http\Requests\Admin\UpdateCashierPermissionsRequest;
use App\Models\User;
use App\Services\Team\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $business = $request->user()->business;

        $members = $business->members()
            ->orderByRaw('case when role = ? then 0 else 1 end', [User::ROLE_ADMIN])
            ->orderBy('name')
            ->get();

        $lastSeen = DB::table('sessions')
            ->whereIn('user_id', $members->pluck('id'))
            ->selectRaw('user_id, max(last_activity) as last_activity')
            ->groupBy('user_id')
            ->pluck('last_activity', 'user_id');

        return view('admin.team', [
            'business' => $business,
            'members' => $members,
            'lastSeen' => $lastSeen,
            'seatsLeft' => $business->staffSeatsLeft(),
            'permissions' => $business->cashierPermissionOptions(),
            'permissionValues' => $business->resolvedSettings()['cashier_permissions'],
        ]);
    }

    public function store(InviteStaffRequest $request, TeamService $team): RedirectResponse
    {
        ['user' => $staff, 'emailed' => $emailed] = $team->invite($request->user()->business, $request->validated());

        if ($request->filled('password')) {
            return back()->with('status', "{$staff->name} can log in now with the password you set. Share it with them in person.");
        }

        if (! $emailed) {
            return back()->withErrors([
                'email' => "{$staff->name} was added, but the invite email couldn't be sent. Use “Set password” to give them a password yourself.",
            ]);
        }

        return back()->with('status', "Invite sent to {$staff->email}.");
    }

    public function resend(Request $request, User $user, TeamService $team): RedirectResponse
    {
        abort_unless($user->business_id === $request->user()->business_id && $user->isStaff(), 404);

        if (! $team->resendInvite($request->user()->business, $user)) {
            return back()->withErrors(['email' => "The invite email couldn't be sent. Use “Set password” to give {$user->name} a password yourself."]);
        }

        return back()->with('status', "Invite sent again to {$user->email}.");
    }

    /**
     * The owner sets a cashier's password directly (no email needed).
     */
    public function setPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->business_id === $request->user()->business_id && $user->isStaff(), 404);

        $validated = $request->validateWithBag('staffPassword', [
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return back()->with('status', "New password set for {$user->name}. Share it with them in person.");
    }

    public function destroy(Request $request, User $user, TeamService $team): RedirectResponse
    {
        abort_unless($user->business_id === $request->user()->business_id, 404);

        $team->remove($request->user()->business, $user);

        return back()->with('status', "{$user->name} was removed. Their past orders keep their name.");
    }

    public function updatePermissions(UpdateCashierPermissionsRequest $request, TeamService $team): RedirectResponse
    {
        $team->updateCashierPermissions($request->user()->business, $request->validated('permissions'));

        return back()->with('status', 'Cashier permissions saved.');
    }
}
