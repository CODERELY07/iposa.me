<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLine;
use App\Services\Audit\RecipeFixService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Recipes use less than set": lower the recipes, or leave them as they are.
 */
class RecipeFixController extends Controller
{
    public function apply(Request $request, AuditLine $auditLine, RecipeFixService $fixes): RedirectResponse
    {
        $this->authorizeLine($request, $auditLine);

        $count = $fixes->apply($auditLine, $request->user());

        return back()->with('status', "Updated {$count} ".Str::plural('recipe', $count)." that use {$auditLine->item->name}.");
    }

    public function dismiss(Request $request, AuditLine $auditLine, RecipeFixService $fixes): RedirectResponse
    {
        $this->authorizeLine($request, $auditLine);

        $fixes->dismiss($auditLine);

        return back()->with('status', 'Recipes left as they are.');
    }

    /**
     * Audit lines carry no business of their own: check it through the audit.
     */
    private function authorizeLine(Request $request, AuditLine $auditLine): void
    {
        abort_unless($auditLine->audit()->withoutGlobalScopes()->value('business_id') === $request->user()->business_id, 404);
    }
}
