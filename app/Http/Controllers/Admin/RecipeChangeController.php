<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecipeChange;
use App\Services\Inventory\RecipeChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The owner's answer to a cashier asking to change what one sale uses.
 */
class RecipeChangeController extends Controller
{
    public function approve(Request $request, RecipeChange $recipeChange, RecipeChangeService $changes): RedirectResponse
    {
        $changes->approve($recipeChange, $request->user());

        return back()->with('status', "Links of {$recipeChange->item->name} updated.");
    }

    public function reject(Request $request, RecipeChange $recipeChange, RecipeChangeService $changes): RedirectResponse
    {
        $changes->reject($recipeChange, $request->user());

        return back()->with('status', "Request for {$recipeChange->item->name} rejected. Nothing changed.");
    }
}
