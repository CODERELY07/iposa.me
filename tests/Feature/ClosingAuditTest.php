<?php

use App\Enums\StockMovementReason;
use App\Models\Audit;
use App\Models\Item;
use App\Reports\DailyLedger;
use App\Services\Inventory\StockService;
use Illuminate\Support\Str;

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

it('lets the owner correct today\'s audit as many times as it wants, and counts each one', function () {
    $this->actingAs($this->owner)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]));
    $this->actingAs($this->owner)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4, $this->mayo->id => 2]))->assertOk();
    $this->actingAs($this->owner)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 3, $this->mayo->id => 2]))->assertOk();

    expect(Audit::withoutGlobalScopes()->sole()->corrections_count)->toBe(2);
});

it('lets a cashier ask to reopen, and the owner\'s approval is spent after one correction', function () {
    $this->actingAs($this->cashier)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]));

    // Denied without asking.
    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 1, $this->mayo->id => 2]))
        ->assertForbidden();

    $this->actingAs($this->cashier)->post(route('audit.reopen.request'))->assertRedirect();
    $audit = Audit::withoutGlobalScopes()->sole();
    expect($audit->reopen_status)->toBe(Audit::REOPEN_PENDING)
        ->and($audit->reopen_requested_by_name)->toBe($this->cashier->name);

    // Still forbidden while pending.
    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 1, $this->mayo->id => 2]))
        ->assertForbidden();

    $this->actingAs($this->owner)->post(route('admin.audits.reopen.approve', $audit))->assertRedirect();
    expect($audit->refresh()->reopen_status)->toBe(Audit::REOPEN_APPROVED);

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 1, $this->mayo->id => 2]))
        ->assertOk();

    // Spent: the approval doesn't carry over to a second correction.
    $audit->refresh();
    expect($audit->reopen_status)->toBeNull()->and($audit->corrections_count)->toBe(1);

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 0.5, $this->mayo->id => 2]))
        ->assertForbidden();
});

it('lets the owner deny a reopen request', function () {
    $this->actingAs($this->cashier)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]));
    $this->actingAs($this->cashier)->post(route('audit.reopen.request'));

    $audit = Audit::withoutGlobalScopes()->sole();
    $this->actingAs($this->owner)->post(route('admin.audits.reopen.reject', $audit))->assertRedirect();

    expect($audit->refresh()->reopen_status)->toBe(Audit::REOPEN_DENIED);

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 1, $this->mayo->id => 2]))
        ->assertForbidden();
});

it('closes the day only once when a count saved offline is replayed', function () {
    $payload = countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]) + [
        'uuid' => (string) Str::uuid(),
        'counted_at' => now()->subMinutes(20)->toIso8601String(),
    ];

    $this->actingAs($this->cashier)->postJson(route('audit.store'), $payload)->assertOk();
    $this->actingAs($this->cashier)->postJson(route('audit.store'), $payload)->assertOk();

    expect(Audit::withoutGlobalScopes()->count())->toBe(1)
        ->and(Audit::withoutGlobalScopes()->sole()->corrections_count)->toBe(0)
        ->and(Audit::withoutGlobalScopes()->sole()->uuid)->toBe($payload['uuid'])
        ->and((float) $this->menu['oil']->refresh()->on_hand)->toBe(4.5);
});

it('closes the day the shelf was counted, even when it is sent later', function () {
    $countedAt = now()->subDay()->setTime(21, 30);

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]) + ['counted_at' => $countedAt->toIso8601String()])
        ->assertOk();

    $audit = Audit::withoutGlobalScopes()->sole();

    expect($audit->date->toDateString())->toBe($countedAt->toDateString())
        ->and($audit->submitted_at->equalTo($countedAt->startOfMinute()) || $audit->submitted_at->diffInSeconds($countedAt) < 2)->toBeTrue();
});

it('measures a count saved offline against what the system held when it was counted', function () {
    // Counted an hour ago at 4.5 of 5. Since then another phone synced a sale that took 1 off the shelf.
    app(StockService::class)->apply($this->owner->business, [$this->menu['oil']->id => -1.0], StockMovementReason::Sale, [], now()->subMinutes(10));
    expect((float) $this->menu['oil']->refresh()->on_hand)->toBe(4.0);

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]) + ['counted_at' => now()->subHour()->toIso8601String()])
        ->assertOk();

    $line = Audit::withoutGlobalScopes()->sole()->lines->firstWhere('item_id', $this->menu['oil']->id);

    // Expected was 5 then, so 0.5 was used. Not a phantom restock against today's 4.
    expect((float) $line->expected)->toBe(5.0)
        ->and((float) $line->used)->toBe(0.5)
        ->and((float) $line->restocked)->toBe(0.0)
        // The shelf now holds what was counted, less the sale that came after.
        ->and((float) $this->menu['oil']->refresh()->on_hand)->toBe(3.5);
});

it('still measures against the moment counted when the offline count is sent seconds later', function () {
    // Counted 30 seconds ago; a sale from another phone landed 10 seconds ago, after the count.
    app(StockService::class)->apply($this->owner->business, [$this->menu['oil']->id => -1.0], StockMovementReason::Sale, [], now()->subSeconds(10));

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4.5, $this->mayo->id => 2]) + ['counted_at' => now()->subSeconds(30)->toIso8601String()])
        ->assertOk();

    $line = Audit::withoutGlobalScopes()->sole()->lines->firstWhere('item_id', $this->menu['oil']->id);

    expect((float) $line->expected)->toBe(5.0)
        ->and((float) $line->restocked)->toBe(0.0)
        ->and((float) $this->menu['oil']->refresh()->on_hand)->toBe(3.5);
});

it('compares with the present count when the audit is sent straight away', function () {
    app(StockService::class)->apply($this->owner->business, [$this->menu['oil']->id => -1.0], StockMovementReason::Sale, [], now()->subMinutes(10));

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 3.5, $this->mayo->id => 2]) + ['counted_at' => now()->subSeconds(20)->toIso8601String()])
        ->assertOk();

    $line = Audit::withoutGlobalScopes()->sole()->lines->firstWhere('item_id', $this->menu['oil']->id);

    expect((float) $line->expected)->toBe(4.0)->and((float) $line->used)->toBe(0.5);
});

it('refuses a count saved offline from long ago or the future', function () {
    foreach ([now()->subDays(4), now()->addHour()] as $when) {
        $this->actingAs($this->cashier)
            ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4, $this->mayo->id => 2]) + ['counted_at' => $when->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('counted_at');
    }
});

it('does not let a cashier overwrite a day another device already closed', function () {
    $this->actingAs($this->owner)->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 4, $this->mayo->id => 2]))->assertOk();

    $this->actingAs($this->cashier)
        ->postJson(route('audit.store'), countsFor([$this->menu['oil']->id => 3, $this->mayo->id => 1]) + ['uuid' => (string) Str::uuid(), 'counted_at' => now()->subMinutes(5)->toIso8601String()])
        ->assertForbidden();

    expect((float) $this->menu['oil']->refresh()->on_hand)->toBe(4.0);
});

it('hands the audit page the time it was loaded, for the offline notice', function () {
    $this->actingAs($this->cashier)->get(route('audit'))
        ->assertOk()
        ->assertSee('loadedAt', false);
});
