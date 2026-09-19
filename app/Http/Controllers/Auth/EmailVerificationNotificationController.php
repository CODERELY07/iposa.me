<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SafeMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     * If mail can't be sent, the verify page offers "ask an iPOSa agent" instead.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $mailed = SafeMail::attempt(fn () => $request->user()->sendEmailVerificationNotification());

        return back()->with('status', $mailed ? 'verification-link-sent' : 'verification-link-failed');
    }
}
