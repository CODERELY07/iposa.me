<?php

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;

it('pauses the shop when the trial ends, but keeps billing and exports open', function () {
    $owner = shopOwner(['status' => BusinessStatus::Trial, 'due_date' => now()->subDay()]);

    $this->actingAs($owner)->get(route('admin.dashboard'))->assertRedirect(route('admin.settings'));
    $this->actingAs($owner)->get(route('admin.settings'))->assertOk()->assertSee('trial has ended');
    $this->actingAs($owner)->get(route('admin.exports.download', 'menu'))->assertOk();
});

it('shows cashiers a paused screen when payment is overdue', function () {
    $owner = shopOwner(['status' => BusinessStatus::PastDue, 'due_date' => now()->subDays(3)]);

    $this->actingAs(cashierOf($owner))->get(route('pos'))->assertStatus(402)->assertSee('The register is paused');
});

it('logs out everyone of a suspended shop', function () {
    $owner = shopOwner(['status' => BusinessStatus::Suspended]);

    $this->actingAs($owner)->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('moves overdue trials and subscriptions to past due every day', function () {
    $expiredTrial = shopOwner(['status' => BusinessStatus::Trial, 'due_date' => now()->subHour()])->business;
    $expiredPaid = shopOwner(['status' => BusinessStatus::Active, 'due_date' => now()->subDay()])->business;
    $current = shopOwner(['status' => BusinessStatus::Active, 'due_date' => now()->addDays(5)])->business;

    $this->artisan('businesses:mark-overdue')->assertSuccessful();

    expect($expiredTrial->refresh()->status)->toBe(BusinessStatus::PastDue)
        ->and($expiredPaid->refresh()->status)->toBe(BusinessStatus::PastDue)
        ->and($current->refresh()->status)->toBe(BusinessStatus::Active);
});

it('lets the operator extend a trial and suspend with a password', function () {
    $operator = User::factory()->superAdmin()->create();
    $business = shopOwner(['status' => BusinessStatus::PastDue, 'due_date' => now()->subDay()])->business;

    $this->actingAs($operator)->post(route('super_admin.businesses.extend-trial', $business), ['days' => 7])->assertRedirect();
    expect($business->refresh()->status)->toBe(BusinessStatus::Trial)
        ->and($business->daysUntilDue())->toBe(7);

    $this->actingAs($operator)->post(route('super_admin.businesses.suspend', $business), ['reason' => 'Abuse', 'password' => 'wrong'])
        ->assertSessionHasErrors('password');
    $this->actingAs($operator)->post(route('super_admin.businesses.suspend', $business), ['reason' => 'Abuse', 'password' => 'password'])
        ->assertSessionHasNoErrors();
    expect($business->refresh()->isSuspended())->toBeTrue();

    $this->actingAs($operator)->post(route('super_admin.businesses.unsuspend', $business));
    expect($business->refresh()->status)->toBe(BusinessStatus::Trial);
});

it('starts every new sign-up on a linked 14-day trial', function () {
    $this->post('/register', [
        'name' => 'Maria Santos', 'email' => 'maria@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        'business_name' => "Kape't Burger", 'business_type' => 'Burger & fast food',
    ]);

    $owner = User::where('email', 'maria@example.com')->sole();
    expect($owner->role)->toBe(User::ROLE_ADMIN)
        ->and($owner->business)->toBeInstanceOf(Business::class)
        ->and($owner->business->user_id)->toBe($owner->id)
        ->and($owner->business->daysUntilDue())->toBe(14);
});
