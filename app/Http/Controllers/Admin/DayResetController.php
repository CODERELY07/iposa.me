<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DayResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DayResetController extends Controller
{
    /**
     * Undo today entirely, because something went wrong. The owner types the shop's
     * name to confirm, so this can never happen from a stray click.
     *
     * @throws ValidationException
     */
    public function __invoke(Request $request, DayResetService $reset): RedirectResponse
    {
        $business = $request->user()->business;

        $request->validate(['confirmation' => ['required', 'string']], [], ['confirmation' => 'confirmation']);

        if (trim((string) $request->input('confirmation')) !== $business->business_name) {
            throw ValidationException::withMessages([
                'confirmation' => "Type your shop's name exactly to reset today.",
            ]);
        }

        $result = $reset->resetToday($business);

        $status = sprintf(
            'Today is reset: %d %s removed, stock restored for %d %s%s, and %d %s removed.',
            $result['orders'], Str::plural('order', $result['orders']),
            $result['items'], Str::plural('item', $result['items']),
            $result['audit'] ? ', tonight\'s closing audit removed' : '',
            $result['expenses'], Str::plural('expense', $result['expenses']),
        );

        return redirect()->route('admin.dashboard')->with('status', $status);
    }
}
