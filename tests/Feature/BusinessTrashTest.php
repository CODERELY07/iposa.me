<?php

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * Removing a shop happens in two deliberate steps: the trash, then erasing.
 */
beforeEach(function (): void {
    $this->operator = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'business_id' => null]);
    $this->owner = shopOwner(['business_name' => 'Closed Cafe']);
    $this->business = $this->owner->business;
});

it('moves a business to the trash with a reason', function (): void {
    actingAs($this->operator)
        ->delete(route('super_admin.businesses.destroy', $this->business), ['reason' => 'Closed down'])
        ->assertRedirect(route('super_admin.businesses.index'));

    $business = Business::withTrashed()->find($this->business->id);

    expect($business->trashed())->toBeTrue()
        ->and($business->deletion_reason)->toBe('Closed down');

    // Gone from the console list and from the platform's numbers.
    // (The row is a link to the shop; the flash message names it, so check the link.)
    actingAs($this->operator)->get(route('super_admin.businesses.index'))
        ->assertOk()
        ->assertDontSee(route('super_admin.businesses.show', $business->id));

    expect(Business::query()->count())->toBe(0);
});

it('locks out the people who worked there', function (): void {
    $cashier = cashierOf($this->owner);

    actingAs($this->operator)->delete(route('super_admin.businesses.destroy', $this->business));

    // fresh(): the in-memory model still holds the business it had before.
    actingAs($this->owner->fresh())->get(route('admin.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn (string $message) => str_contains($message, 'has been removed'));

    actingAs($cashier->fresh())->get(route('pos'))->assertRedirect(route('login'));
});

it('restores a business from the trash', function (): void {
    actingAs($this->operator)->delete(route('super_admin.businesses.destroy', $this->business));

    actingAs($this->operator)
        ->patch(route('super_admin.businesses.restore', $this->business->id))
        ->assertRedirect(route('super_admin.businesses.show', $this->business->id));

    $business = Business::find($this->business->id);

    expect($business)->not->toBeNull()
        ->and($business->trashed())->toBeFalse()
        ->and($business->deletion_reason)->toBeNull();

    actingAs($this->owner->fresh())->get(route('admin.dashboard'))->assertOk();
});

it('lists what erasing a shop would take with it', function (): void {
    Item::withoutGlobalScopes()->create([
        'business_id' => $this->business->id,
        'kind' => 'piece', 'name' => 'Cups', 'unit' => 'pc', 'on_hand' => 10, 'unit_cost' => 2,
    ]);

    actingAs($this->operator)->delete(route('super_admin.businesses.destroy', $this->business));

    actingAs($this->operator)->get(route('super_admin.businesses.trash'))
        ->assertOk()
        ->assertSee('Closed Cafe')
        ->assertSee('Erase for good');
});

it('erases a trashed shop, its data and its accounts', function (): void {
    $cashier = cashierOf($this->owner);
    $item = Item::withoutGlobalScopes()->create([
        'business_id' => $this->business->id,
        'kind' => 'piece', 'name' => 'Cups', 'unit' => 'pc', 'on_hand' => 10, 'unit_cost' => 2,
    ]);

    actingAs($this->operator)->delete(route('super_admin.businesses.destroy', $this->business));

    actingAs($this->operator)
        ->delete(route('super_admin.businesses.erase', $this->business->id), ['confirmation' => 'Closed Cafe'])
        ->assertRedirect(route('super_admin.businesses.trash'));

    expect(Business::withTrashed()->find($this->business->id))->toBeNull()
        ->and(Item::withoutGlobalScopes()->find($item->id))->toBeNull()
        ->and(User::find($this->owner->id))->toBeNull()
        ->and(User::find($cashier->id))->toBeNull()
        ->and(User::find($this->operator->id))->not->toBeNull();
});

it('only erases when the shop name is typed exactly', function (): void {
    actingAs($this->operator)->delete(route('super_admin.businesses.destroy', $this->business));

    actingAs($this->operator)
        ->delete(route('super_admin.businesses.erase', $this->business->id), ['confirmation' => 'closed cafe'])
        ->assertSessionHasErrors('confirmation');

    actingAs($this->operator)
        ->delete(route('super_admin.businesses.erase', $this->business->id), [])
        ->assertSessionHasErrors('confirmation');

    expect(Business::withTrashed()->find($this->business->id))->not->toBeNull();
});

it('never erases a business that is not in the trash', function (): void {
    actingAs($this->operator)
        ->delete(route('super_admin.businesses.erase', $this->business->id), ['confirmation' => 'Closed Cafe'])
        ->assertSessionHasErrors('business');

    expect(Business::find($this->business->id))->not->toBeNull();
});

it('keeps a trashed shop out of plans, metrics and search', function (): void {
    Business::factory()->active()->create(['business_name' => 'Still Trading', 'plan' => 'negosyo', 'plan_price' => 999]);
    $this->business->forceFill(['status' => BusinessStatus::Active, 'plan' => 'negosyo', 'plan_price' => 999])->save();

    actingAs($this->operator)->delete(route('super_admin.businesses.destroy', $this->business));

    actingAs($this->operator)->get(route('super_admin.dashboard'))
        ->assertOk()
        ->assertSee('Still Trading')
        ->assertDontSee(route('super_admin.businesses.show', $this->business->id));

    actingAs($this->operator)->get(route('super_admin.businesses.index', ['q' => 'Closed']))
        ->assertOk()
        ->assertDontSee(route('super_admin.businesses.show', $this->business->id));

    actingAs($this->operator)->get(route('super_admin.plans'))->assertOk()->assertSee('Negosyo');
});

it('keeps shop owners out of the trash', function (): void {
    actingAs($this->owner)->get(route('super_admin.businesses.trash'))->assertRedirect(route('admin.dashboard'));
    actingAs($this->owner)->delete(route('super_admin.businesses.destroy', $this->business))->assertRedirect(route('admin.dashboard'));

    expect(Business::find($this->business->id))->not->toBeNull();
});
