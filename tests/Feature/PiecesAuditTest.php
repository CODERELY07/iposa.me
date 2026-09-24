<?php

use App\Models\AuditLine;
use App\Reports\DailyLedger;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->menu = demoMenu($this->owner);
    $this->cashier = cashierOf($this->owner);
});

it('counts only bulk and liquids at closing by default', function () {
    $this->actingAs($this->cashier)->get(route('audit'))
        ->assertOk()
        ->assertSee('Cooking oil')
        ->assertDontSee('Burger bun');

    $this->actingAs($this->cashier)->postJson(route('audit.store'), ['counts' => [['item_id' => $this->menu['oil']->id, 'counted' => 4]]])->assertOk();
});

it('saves the count pieces setting', function () {
    $this->actingAs($this->owner)->patch(route('admin.settings.register'), [
        'payment_methods' => ['cash'],
        'audit_reminder_time' => '21:30',
        'default_low_threshold' => 10,
        'audit_pieces' => 1,
    ])->assertRedirect();

    expect($this->business->refresh()->auditsPieces())->toBeTrue();

    $this->actingAs($this->owner)->get(route('admin.settings'))->assertOk()->assertSee('Count pieces at closing');
});

it('counts pieces too when the shop asks, and costs missing ones', function () {
    $this->business->update(['settings' => ['audit_pieces' => true]]);

    $this->actingAs($this->cashier)->get(route('audit'))->assertOk()->assertSee('Burger bun');
    $this->actingAs($this->owner)->get(route('audit'))->assertOk()->assertSee('Used or missing today');

    // Only the oil: every counted item is required.
    $this->actingAs($this->cashier)->postJson(route('audit.store'), ['counts' => [['item_id' => $this->menu['oil']->id, 'counted' => 5]]])
        ->assertUnprocessable();

    $counts = [
        ['item_id' => $this->menu['oil']->id, 'counted' => 5],
        ['item_id' => $this->menu['bun']->id, 'counted' => 96],
        ['item_id' => $this->menu['patty']->id, 'counted' => 100],
        ['item_id' => $this->menu['cup16']->id, 'counted' => 50],
        ['item_id' => $this->menu['cup22']->id, 'counted' => 50],
    ];

    $this->actingAs($this->cashier)->postJson(route('audit.store'), ['counts' => $counts])->assertOk();

    expect((float) AuditLine::query()->where('item_id', $this->menu['bun']->id)->sole()->used)->toBe(4.0)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(96.0)
        ->and(app(DailyLedger::class)->forRange($this->business, today(), today())->first()['bulk'])->toBe(30.0);
});
