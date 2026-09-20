<?php

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Plan;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * The operator fixing one shop: its details, its subscription and the owner's account.
 */
beforeEach(function (): void {
    $this->operator = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'business_id' => null]);
    $this->owner = shopOwner(['business_name' => 'Kapet Burger', 'plan' => 'negosyo', 'plan_price' => 999]);
    $this->business = $this->owner->business;
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function businessPayload(Business $business, array $overrides = []): array
{
    return array_merge([
        'business_name' => $business->business_name,
        'business_type' => $business->business_type,
        'address' => $business->address,
        'tin' => $business->tin,
        'receipt_footer' => $business->receipt_footer,
        'plan' => $business->plan,
        'plan_price' => $business->monthlyPrice(),
        'status' => $business->status->value,
        'start_date' => $business->start_date?->format('Y-m-d'),
        'due_date' => $business->due_date?->format('Y-m-d'),
        'owner_name' => $business->owner?->name,
        'owner_email' => $business->owner?->email,
    ], $overrides);
}

it('opens the edit screen with the shop, subscription and owner', function (): void {
    actingAs($this->operator)->get(route('super_admin.businesses.edit', $this->business))
        ->assertOk()
        ->assertSee('Kapet Burger')
        ->assertSee($this->owner->email)
        ->assertSee('Subscription');
});

it('saves the shop details and the subscription by hand', function (): void {
    actingAs($this->operator)
        ->put(route('super_admin.businesses.update', $this->business), businessPayload($this->business, [
            'business_name' => "Kape't Burger",
            'business_type' => 'Bakery',
            'address' => 'Marikina',
            'tin' => '123-456-789',
            'receipt_footer' => 'Salamat po!',
            'status' => BusinessStatus::Active->value,
            'start_date' => '2026-01-05',
            'due_date' => '2026-12-31',
            'plan_price' => 750,
        ]))
        ->assertRedirect(route('super_admin.businesses.show', $this->business));

    $business = $this->business->refresh();

    expect($business->business_name)->toBe("Kape't Burger")
        ->and($business->business_type)->toBe('Bakery')
        ->and($business->tin)->toBe('123-456-789')
        ->and($business->status)->toBe(BusinessStatus::Active)
        ->and($business->start_date->format('Y-m-d'))->toBe('2026-01-05')
        ->and($business->due_date->format('Y-m-d'))->toBe('2026-12-31')
        ->and($business->monthlyPrice())->toBe(750.0);
});

it('moves a shop to another plan, and the plan gates follow', function (): void {
    expect($this->business->hasFeature('reports'))->toBeTrue();

    actingAs($this->operator)->put(route('super_admin.businesses.update', $this->business), businessPayload($this->business, [
        'plan' => 'tindahan',
        'plan_price' => 499,
    ]));

    expect($this->business->refresh()->hasFeature('reports'))->toBeFalse();

    actingAs($this->owner->refresh())->get(route('admin.reports'))->assertRedirect();
});

it('fixes a mistyped owner email and makes it unverified again', function (): void {
    actingAs($this->operator)
        ->put(route('super_admin.businesses.update', $this->business), businessPayload($this->business, [
            'owner_name' => 'Maria Santos',
            'owner_email' => 'maria@kapetburger.ph',
        ]))
        ->assertRedirect();

    $owner = $this->owner->refresh();

    expect($owner->name)->toBe('Maria Santos')
        ->and($owner->email)->toBe('maria@kapetburger.ph')
        ->and($owner->email_verified_at)->toBeNull();

    // The operator can then verify it with the usual one-button flow.
    actingAs($this->operator)->post(route('super_admin.users.verify', $owner))->assertRedirect();

    expect($owner->refresh()->email_verified_at)->not->toBeNull();
});

it('keeps a verified owner verified when the email does not change', function (): void {
    actingAs($this->operator)->put(route('super_admin.businesses.update', $this->business), businessPayload($this->business, [
        'owner_name' => 'Renamed Only',
    ]));

    expect($this->owner->refresh()->email_verified_at)->not->toBeNull();
});

it('refuses an email another account already uses, and a due date before the start', function (): void {
    $other = User::factory()->create(['email' => 'taken@example.com']);

    actingAs($this->operator)
        ->put(route('super_admin.businesses.update', $this->business), businessPayload($this->business, ['owner_email' => $other->email]))
        ->assertSessionHasErrors('owner_email');

    actingAs($this->operator)
        ->put(route('super_admin.businesses.update', $this->business), businessPayload($this->business, [
            'start_date' => '2026-06-01',
            'due_date' => '2026-05-01',
        ]))
        ->assertSessionHasErrors('due_date');

    expect($this->owner->refresh()->email)->not->toBe('taken@example.com');
});

it('cannot set a shop to suspended from the edit screen, or edit a suspended shop', function (): void {
    actingAs($this->operator)
        ->put(route('super_admin.businesses.update', $this->business), businessPayload($this->business, ['status' => BusinessStatus::Suspended->value]))
        ->assertSessionHasErrors('status');

    $suspended = Business::factory()->suspended()->create();

    actingAs($this->operator)
        ->put(route('super_admin.businesses.update', $suspended), businessPayload($suspended, ['business_name' => 'Renamed']))
        ->assertSessionHasErrors('status');

    expect($suspended->refresh()->business_name)->not->toBe('Renamed');
});

it('keeps shop owners out of the business editor', function (): void {
    actingAs($this->owner)->get(route('super_admin.businesses.edit', $this->business))->assertRedirect(route('admin.dashboard'));
    actingAs($this->owner)->put(route('super_admin.businesses.update', $this->business), businessPayload($this->business, ['business_name' => 'Mine now']))
        ->assertRedirect(route('admin.dashboard'));

    expect($this->business->refresh()->business_name)->toBe('Kapet Burger');
});

it('does not offer editing while a shop is suspended', function (): void {
    $suspended = Business::factory()->suspended()->create();

    actingAs($this->operator)->get(route('super_admin.businesses.show', $suspended))
        ->assertOk()
        ->assertDontSee(route('super_admin.businesses.edit', $suspended));
});

it('leaves an archived plan selectable for the shop already on it', function (): void {
    $legacy = Plan::factory()->create(['key' => 'legacy', 'name' => 'Legacy', 'price' => 299]);
    $legacy->forceFill(['archived_at' => now()])->save();

    $this->business->forceFill(['plan' => 'legacy', 'plan_price' => 299])->save();

    actingAs($this->operator)->get(route('super_admin.businesses.edit', $this->business))
        ->assertOk()
        ->assertSee('Legacy')
        ->assertSee('(archived)');
});
