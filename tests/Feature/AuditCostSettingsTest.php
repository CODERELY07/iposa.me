<?php

use App\Models\Item;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->business = $this->owner->business;
    $this->ketchup = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'bulk', 'name' => 'Ketchup', 'unit' => 'ml', 'on_hand' => 3000, 'unit_cost' => 0.05]);
    $this->oil = Item::withoutGlobalScopes()->create(['business_id' => $this->business->id, 'kind' => 'bulk', 'name' => 'Cooking oil', 'unit' => 'ml', 'on_hand' => 5000, 'unit_cost' => 0.1]);
});

it('lists everything counted at closing, on by default', function () {
    $this->actingAs($this->owner)->get(route('admin.audit-cost-settings'))
        ->assertOk()
        ->assertSee('Ketchup')
        ->assertSee('Cooking oil');

    expect($this->ketchup->refresh()->include_audit_cost)->toBeTrue()
        ->and($this->oil->refresh()->include_audit_cost)->toBeTrue();
});

it('lets the owner check some items and uncheck others', function () {
    $this->actingAs($this->owner)->put(route('admin.audit-cost-settings.update'), [
        'included' => [$this->oil->id],
    ])->assertRedirect(route('admin.audit-cost-settings'));

    expect($this->ketchup->refresh()->include_audit_cost)->toBeFalse()
        ->and($this->oil->refresh()->include_audit_cost)->toBeTrue();
});

it('turns every item off when none are checked', function () {
    $this->actingAs($this->owner)->put(route('admin.audit-cost-settings.update'), [])->assertRedirect();

    expect($this->ketchup->refresh()->include_audit_cost)->toBeFalse()
        ->and($this->oil->refresh()->include_audit_cost)->toBeFalse();
});

it('turns every item back on when all are checked', function () {
    $this->ketchup->update(['include_audit_cost' => false]);
    $this->oil->update(['include_audit_cost' => false]);

    $this->actingAs($this->owner)->put(route('admin.audit-cost-settings.update'), [
        'included' => [$this->ketchup->id, $this->oil->id],
    ])->assertRedirect();

    expect($this->ketchup->refresh()->include_audit_cost)->toBeTrue()
        ->and($this->oil->refresh()->include_audit_cost)->toBeTrue();
});

it('never lets one shop change another shop\'s items', function () {
    $otherOwner = shopOwner();
    $otherItem = Item::withoutGlobalScopes()->create(['business_id' => $otherOwner->business_id, 'kind' => 'bulk', 'name' => 'Their oil', 'unit' => 'ml', 'on_hand' => 1000, 'unit_cost' => 0.1]);

    $this->actingAs($this->owner)->put(route('admin.audit-cost-settings.update'), [
        'included' => [$this->ketchup->id, $otherItem->id],
    ])->assertRedirect();

    expect($otherItem->refresh()->include_audit_cost)->toBeTrue();
});
