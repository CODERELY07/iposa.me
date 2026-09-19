<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualVerificationRequestController extends Controller
{
    /**
     * The verification email didn't arrive: ask an iPOSa agent to verify the account.
     * The request shows up for the platform operator under Verifications.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $user->forceFill(['verification_requested_at' => now()])->save();

        return back()->with('status', 'manual-verification-requested');
    }
}
