<?php

use App\Models\Audit;
use App\Models\Item;
use App\Reports\DailyLedger;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
    $this->mayo = Item::withoutGlobalScopes()->create(['business_id' => $this->owner->business_id, 'kind' => 'bulk', 'name' => 'Mayonnaise', 'unit' => '5kg tub', 'on_hand' => 2, 'unit_cost' => 890]);
});

function countsFor(array $counts): array
{
    return ['counts' => collect($counts)->map(fn ($counted, $itemId) => ['item_id' => $itemId, 'counted' => $counted])->values()->all()];
}

it('saves the shelf count and prices what was used', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]) + ['started_at' => now()->subMinute()->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('audit.usage_cost', null);

    expect((float) $this->menu['oil']->refresh()->on_hand)->toBe(4.5);

    $audit = Audit::withoutGlobalScopes()->with('lines')->sole();
    expect($audit->counted_by)->toBe($this->cashier->name)
        ->and($audit->duration_seconds)->toBeGreaterThanOrEqual(59)
        ->and($audit->usageCost())->toBe(72.5);

    $row = app(DailyLedger::class)->forRange($this->owner->business, today(), today())->first();
    expect($row['bulk'])->toBe(72.5)->and($row['audited'])->toBeTrue();
});

it('shows the owner the usage cost', function () {
    $this->actingAs($this->owner)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4, $this->mayo->id => 1.5]))
        ->assertJsonPath('audit.usage_cost', 590);
});

it('records a count above the system as a restock, not negative usage', function () {
    $this->actingAs($this->owner)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 8, $this->mayo->id => 2]));

    $line = Audit::withoutGlobalScopes()->sole()->lines->firstWhere('item_id', $this->menu['oil']->id);
    expect((float) $line->used)->toBe(0.0)->and((float) $line->restocked)->toBe(3.0);
});

it('needs every bulk item counted', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('counts');
});

it('does not let a cashier redo today\'s audit', function () {
    $this->actingAs($this->cashier)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]));

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 1, $this->mayo->id => 2]))
        ->assertForbidden();
});

it('lets the owner correct today\'s counts, moving stock only by the difference', function () {
    $this->actingAs($this->cashier)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]));
    $this->actingAs($this->owner)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4, $this->mayo->id => 2]))->assertOk();

    expect((float) $this->menu['oil']->refresh()->on_hand)->toBe(4.0)
        ->and(Audit::withoutGlobalScopes()->count())->toBe(1);

    $line = Audit::withoutGlobalScopes()->sole()->lines->firstWhere('item_id', $this->menu['oil']->id);
    expect((float) $line->expected)->toBe(5.0)->and((float) $line->used)->toBe(1.0);
});

it('hides the audit from cashiers when the owner turns it off', function () {
    $this->owner->business->update(['settings' => ['cashier_permissions' => ['run_audit' => false]]]);

    $this->actingAs($this->cashier)->get(route('audit'))->assertForbidden();
});
