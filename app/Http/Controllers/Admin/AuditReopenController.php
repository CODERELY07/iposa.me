<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Services\Audit\ClosingAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The owner's answer to a cashier asking to reopen tonight's closed count.
 */
class AuditReopenController extends Controller
{
    public function approve(Request $request, Audit $audit, ClosingAuditService $audits): RedirectResponse
    {
        $audits->decideReopen($audit, $request->user(), true);

        return back()->with('status', "Reopened tonight's count for {$audit->reopen_requested_by_name}.");
    }

    public function reject(Request $request, Audit $audit, ClosingAuditService $audits): RedirectResponse
    {
        $audits->decideReopen($audit, $request->user(), false);

        return back()->with('status', 'Request denied. Tonight\'s count stays as is.');
    }
}
