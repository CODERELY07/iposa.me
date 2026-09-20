<?php

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\Business;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * Plans are managed by the platform operator. The rule that matters:
 * a price change never reaches a shop mid-period, only at their next renewal.
 */
beforeEach(function (): void {
    $this->operator = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'business_id' => null]);
});

it('creates a plan owners can switch to', function (): void {
    actingAs($this->operator)
        ->post(route('super_admin.plans.store'), [
            'name' => 'Panaderia',
            'key' => 'panaderia',
            'price' => 749,
            'pitch' => 'For bakeries with two shifts',
            'staff_limit' => 5,
            'sort' => 15,
            'features' => ['expenses' => '1', 'reports' => '1', 'recipes' => '0'],
            'feature_list' => "Register (POS)\nExpenses\n\n  Closing audit  ",
        ])
        ->assertRedirect(route('super_admin.plans'));

    $plan = Plan::query()->firstWhere('key', 'panaderia');

    expect((float) $plan->price)->toBe(749.0)
        ->and($plan->staff_limit)->toBe(5)
        ->and($plan->features)->toBe(['expenses' => true, 'reports' => true, 'recipes' => false])
        ->and($plan->feature_list)->toBe(['Register (POS)', 'Expenses', 'Closing audit']);

    $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $business = Business::factory()->create(['user_id' => $owner->id]);
    $owner->forceFill(['business_id' => $business->id])->save();

    actingAs($owner)->patch(route('admin.billing.plan'), ['plan' => 'panaderia'])->assertRedirect();

    expect($business->refresh()->plan)->toBe('panaderia')
        ->and($business->monthlyPrice())->toBe(749.0);
});

it('opens the new-plan and edit screens', function (): void {
    $plan = Plan::factory()->create(['key' => 'editable', 'name' => 'Editable', 'price' => 499, 'feature_list' => ['Register (POS)']]);
    Business::factory()->create(['plan' => 'editable']);

    actingAs($this->operator)->get(route('super_admin.plans.create'))->assertOk()->assertSee('New plan');

    actingAs($this->operator)->get(route('super_admin.plans.edit', $plan))
        ->assertOk()
        ->assertSee('Editable')
        ->assertSee('Register (POS)')
        ->assertSee('shop on this plan', false);
});

it('makes the key from the name and keeps it unique', function (): void {
    actingAs($this->operator)->post(route('super_admin.plans.store'), planPayload(['name' => 'Negosyo Plus', 'key' => null]));

    expect(Plan::query()->where('key', 'negosyo-plus')->exists())->toBeTrue();

    actingAs($this->operator)
        ->post(route('super_admin.plans.store'), planPayload(['name' => 'Negosyo Plus', 'key' => 'negosyo-plus']))
        ->assertSessionHasErrors('key');
});

it('saves a plan with unlimited staff', function (): void {
    actingAs($this->operator)->post(route('super_admin.plans.store'), [
        'name' => 'Walang Limit',
        'key' => 'walang-limit',
        'price' => 1499,
        'unlimited_staff' => '1',
        'features' => ['expenses' => '1', 'reports' => '1', 'recipes' => '1'],
        'feature_list' => 'Unlimited staff',
    ])->assertSessionHasNoErrors();

    $plan = Plan::query()->firstWhere('key', 'walang-limit');

    expect($plan->staff_limit)->toBeNull();

    $business = Business::factory()->create(['plan' => 'walang-limit', 'plan_price' => 1499]);

    expect($business->staffSeatsLeft())->toBeNull();
});

it('never changes the key of an existing plan', function (): void {
    $plan = Plan::factory()->create(['key' => 'tindahan-x', 'name' => 'Tindahan X']);

    actingAs($this->operator)
        ->put(route('super_admin.plans.update', $plan), planPayload(['name' => 'Renamed', 'key' => 'something-else']))
        ->assertRedirect(route('super_admin.plans'));

    expect($plan->refresh()->key)->toBe('tindahan-x')
        ->and($plan->name)->toBe('Renamed');
});

it('keeps a paying shop on the price they agreed to when the plan price rises', function (): void {
    $plan = Plan::factory()->create(['key' => 'negosyo-a', 'price' => 999]);
    $business = Business::factory()->active()->create(['plan' => 'negosyo-a', 'plan_price' => 999]);

    actingAs($this->operator)->put(route('super_admin.plans.update', $plan), planPayload(['name' => $plan->name, 'price' => 1299]));

    $business->refresh();

    expect($business->monthlyPrice())->toBe(999.0)
        ->and($business->priceAtRenewal())->toBe(1299.0);

    // The payment they submit now is still for the old price.
    $owner = $business->owner;
    $owner->forceFill(['business_id' => $business->id, 'role' => User::ROLE_ADMIN])->save();

    actingAs($owner)->post(route('admin.billing.payments.store'), ['method' => 'gcash', 'reference' => '1009 111 222']);

    expect((float) SubscriptionPayment::query()->latest('id')->first()->amount)->toBe(999.0);

    // And the billing card tells them what changes, and when.
    actingAs($owner)->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('From your next renewal', false)
        ->assertSee('1,299');
});

it('applies the new price at the next renewal', function (): void {
    $plan = Plan::factory()->create(['key' => 'negosyo-b', 'price' => 1299]);
    $business = Business::factory()->active()->create(['plan' => 'negosyo-b', 'plan_price' => 999]);

    $payment = $business->subscriptionPayments()->create([
        'plan' => 'negosyo-b',
        'amount' => 999,
        'method' => 'gcash',
        'reference' => '1009 000 111',
        'status' => SubscriptionPaymentStatus::Pending,
    ]);

    actingAs($this->operator)->post(route('super_admin.payments.confirm', $payment))->assertRedirect();

    $business->refresh();

    expect($business->status)->toBe(BusinessStatus::Active)
        ->and($business->monthlyPrice())->toBe(1299.0)
        ->and($business->priceAtRenewal())->toBeNull();
});

it('hides an archived plan from owners but keeps the shops on it working', function (): void {
    $keep = Plan::factory()->create(['key' => 'keep-me', 'price' => 499]);
    $old = Plan::factory()->create(['key' => 'legacy', 'name' => 'Legacy', 'price' => 299, 'features' => ['expenses' => true, 'reports' => true, 'recipes' => true]]);

    $business = Business::factory()->active()->create(['plan' => 'legacy', 'plan_price' => 299]);
    $owner = $business->owner;
    $owner->forceFill(['business_id' => $business->id, 'role' => User::ROLE_ADMIN])->save();

    actingAs($this->operator)->patch(route('super_admin.plans.archive', $old))->assertRedirect();

    expect($old->refresh()->isArchived())->toBeTrue();

    // The shop still works, on the plan it had, at the price it agreed to.
    actingAs($owner)->get(route('admin.settings'))->assertOk()->assertSee('Legacy', false);
    expect($business->refresh()->hasFeature('reports'))->toBeTrue();

    // Nobody can move onto it any more.
    actingAs($owner)->patch(route('admin.billing.plan'), ['plan' => 'legacy'])->assertSessionHasErrors('plan');
    actingAs($owner)->patch(route('admin.billing.plan'), ['plan' => 'keep-me'])->assertSessionHasNoErrors();
});

it('refuses to archive the last available plan', function (): void {
    Plan::query()->delete();
    $only = Plan::factory()->create(['key' => 'only-one']);

    actingAs($this->operator)->patch(route('super_admin.plans.archive', $only))->assertSessionHasErrors('plan');

    expect($only->refresh()->isArchived())->toBeFalse();
});

it('deletes a plan nobody uses and refuses one with shops or payments', function (): void {
    $unused = Plan::factory()->create(['key' => 'unused']);
    $inUse = Plan::factory()->create(['key' => 'in-use']);
    Business::factory()->create(['plan' => 'in-use']);

    $withHistory = Plan::factory()->create(['key' => 'retired']);
    Business::factory()->create(['plan' => 'keep-me'])->subscriptionPayments()->create([
        'plan' => 'retired', 'amount' => 299, 'method' => 'gcash', 'reference' => 'old-1', 'status' => SubscriptionPaymentStatus::Paid,
    ]);

    actingAs($this->operator)->delete(route('super_admin.plans.destroy', $unused))->assertRedirect(route('super_admin.plans'));
    expect(Plan::query()->whereKey($unused->id)->exists())->toBeFalse();

    actingAs($this->operator)->delete(route('super_admin.plans.destroy', $inUse))->assertSessionHasErrors('plan');
    actingAs($this->operator)->delete(route('super_admin.plans.destroy', $withHistory))->assertSessionHasErrors('plan');

    expect(Plan::query()->whereKey($inUse->id)->exists())->toBeTrue()
        ->and(Plan::query()->whereKey($withHistory->id)->exists())->toBeTrue();
});

it('shows plan features on the public pricing section', function (): void {
    Plan::query()->delete();
    Plan::factory()->create(['key' => 'solo', 'name' => 'Solo', 'price' => 299, 'pitch' => 'One counter', 'feature_list' => ['Register (POS)', 'Closing audit']]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Solo')
        ->assertSee('299')
        ->assertSee('One counter');
});

it('tidies a typed key and refuses a negative price or no name', function (): void {
    actingAs($this->operator)
        ->post(route('super_admin.plans.store'), planPayload(['key' => 'Has Spaces', 'name' => 'Spaced']))
        ->assertSessionHasNoErrors();

    expect(Plan::query()->where('key', 'has-spaces')->exists())->toBeTrue();

    actingAs($this->operator)
        ->post(route('super_admin.plans.store'), planPayload(['price' => -5]))
        ->assertSessionHasErrors('price');

    actingAs($this->operator)
        ->post(route('super_admin.plans.store'), planPayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('keeps shop owners out of plan management', function (): void {
    $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $business = Business::factory()->create(['user_id' => $owner->id]);
    $owner->forceFill(['business_id' => $business->id])->save();

    $plan = Plan::factory()->create();

    actingAs($owner)->get(route('super_admin.plans'))->assertRedirect(route('admin.dashboard'));
    actingAs($owner)->get(route('super_admin.plans.create'))->assertRedirect(route('admin.dashboard'));
    actingAs($owner)->put(route('super_admin.plans.update', $plan), planPayload())->assertRedirect(route('admin.dashboard'));
    actingAs($owner)->delete(route('super_admin.plans.destroy', $plan))->assertRedirect(route('admin.dashboard'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function planPayload(array $overrides = []): array
{
    return array_filter(array_merge([
        'name' => 'Some Plan',
        'key' => 'some-plan',
        'price' => 599,
        'pitch' => 'A plan',
        'staff_limit' => 3,
        'sort' => 10,
        'features' => ['expenses' => '1', 'reports' => '0', 'recipes' => '0'],
        'feature_list' => "Register (POS)\nClosing audit",
    ], $overrides), fn ($value) => $value !== null);
}
