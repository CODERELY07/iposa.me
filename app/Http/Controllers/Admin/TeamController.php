<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteStaffRequest;
use App\Http\Requests\Admin\UpdateCashierPermissionsRequest;
use App\Models\Business;
use App\Models\User;
use App\Services\Team\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'permissions' => Business::CASHIER_PERMISSIONS,
            'permissionValues' => $business->resolvedSettings()['cashier_permissions'],
        ]);
    }

    public function store(InviteStaffRequest $request, TeamService $team): RedirectResponse
    {
        $staff = $team->invite($request->user()->business, $request->validated());

        return back()->with('status', "Invite sent to {$staff->email}.");
    }

    public function resend(Request $request, User $user, TeamService $team): RedirectResponse
    {
        abort_unless($user->business_id === $request->user()->business_id && $user->isStaff(), 404);

        $team->resendInvite($request->user()->business, $user);

        return back()->with('status', "Invite sent again to {$user->email}.");
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
