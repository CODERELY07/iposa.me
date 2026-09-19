<?php

namespace App\Support;

use Closure;
use Throwable;

/**
 * Sending email must never break sign-up, invites or password resets.
 * If the mail server is unreachable (e.g. the host blocks SMTP), the error is
 * logged and the caller gets `false` so it can offer the manual fallback.
 */
class SafeMail
{
    /**
     * Run a mail-sending callback. Returns true when it went through.
     */
    public static function attempt(Closure $send): bool
    {
        try {
            $send();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
