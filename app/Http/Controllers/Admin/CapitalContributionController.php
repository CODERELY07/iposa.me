<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCapitalContributionRequest;
use App\Models\CapitalContribution;
use Illuminate\Http\RedirectResponse;

class CapitalContributionController extends Controller
{
    /**
     * Record money the owner put into the business. Equity, not an expense — it
     * never touches Net profit, Cash-basis profit, or any day's ledger row.
     */
    public function store(StoreCapitalContributionRequest $request): RedirectResponse
    {
        CapitalContribution::create([
            'date' => $request->validated('date'),
            'amount' => $request->validated('amount'),
            'note' => $request->validated('note'),
            'user_id' => $request->user()->id,
            'logged_by' => $request->user()->name,
        ]);

        return back()->with('status', 'Capital recorded.');
    }

    public function destroy(CapitalContribution $capitalContribution): RedirectResponse
    {
        $capitalContribution->delete();

        return back()->with('status', 'Removed.');
    }
}
