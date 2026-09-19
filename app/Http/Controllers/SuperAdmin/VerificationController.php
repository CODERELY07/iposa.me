<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    /**
     * Accounts that haven't verified their email. People who asked an agent come first.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->whereNull('email_verified_at')
            ->with('business:id,business_name,business_type')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', '%'.$search.'%')
                ->orWhereLike('email', '%'.$search.'%')))
            ->orderByRaw('case when verification_requested_at is null then 1 else 0 end')
            ->orderByDesc('verification_requested_at')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('super_admin.verifications', [
            'users' => $users,
            'search' => $search,
            'requestedCount' => User::query()->whereNull('email_verified_at')->whereNotNull('verification_requested_at')->count(),
        ]);
    }

    /**
     * Mark the email as verified, as if the person had clicked the link.
     */
    public function verify(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $user->forceFill(['verification_requested_at' => null])->save();

        return back()->with('status', "{$user->email} is verified. They can use iPOSa now.");
    }
}
