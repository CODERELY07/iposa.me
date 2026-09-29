<?php

use App\Models\CapitalContribution;

beforeEach(function () {
    $this->owner = shopOwner();
});

it('records startup capital as equity, separate from expenses and profit', function () {
    $this->actingAs($this->owner)->post(route('admin.capital.store'), [
        'date' => today()->toDateString(), 'amount' => 50000, 'note' => 'Initial investment',
    ])->assertRedirect();

    $contribution = CapitalContribution::withoutGlobalScopes()->sole();
    expect((float) $contribution->amount)->toBe(50000.0)
        ->and($contribution->note)->toBe('Initial investment')
        ->and($contribution->logged_by)->toBe($this->owner->name);
});

it('shows the running total on the reports page, all-time regardless of the period shown', function () {
    CapitalContribution::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'date' => today()->subYear(), 'amount' => 50000, 'logged_by' => $this->owner->name,
    ]);
    CapitalContribution::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'date' => today(), 'amount' => 10000, 'note' => 'Top-up', 'logged_by' => $this->owner->name,
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.reports', ['period' => 'custom', 'from' => today()->toDateString(), 'to' => today()->toDateString()]))
        ->assertOk()
        ->assertViewHas('totalCapital', 60000.0)
        ->assertSee('60,000.00', false)
        ->assertSee('Top-up');
});

it('never affects net profit', function () {
    CapitalContribution::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'date' => today(), 'amount' => 50000, 'logged_by' => $this->owner->name,
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('admin.reports', ['period' => 'custom', 'from' => today()->toDateString(), 'to' => today()->toDateString()]));

    expect($response->viewData('totals'))->toMatchArray(['sales' => 0.0, 'net' => 0.0]);
});

it('removes a contribution', function () {
    $contribution = CapitalContribution::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'date' => today(), 'amount' => 50000, 'logged_by' => $this->owner->name,
    ]);

    $this->actingAs($this->owner)->delete(route('admin.capital.destroy', $contribution))->assertRedirect();

    expect(CapitalContribution::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a future date', function () {
    $this->actingAs($this->owner)->post(route('admin.capital.store'), [
        'date' => today()->addDay()->toDateString(), 'amount' => 1000,
    ])->assertSessionHasErrors('date');
});

it('cannot see or remove another shop\'s capital', function () {
    $contribution = CapitalContribution::withoutGlobalScopes()->create([
        'business_id' => $this->owner->business_id, 'date' => today(), 'amount' => 50000, 'logged_by' => $this->owner->name,
    ]);

    $stranger = shopOwner();
    $this->actingAs($stranger)->get(route('admin.reports'))->assertViewHas('totalCapital', 0.0);
    $this->actingAs($stranger)->delete(route('admin.capital.destroy', $contribution))->assertNotFound();
});
