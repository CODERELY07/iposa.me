<?php

use App\Models\Order;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->owner = shopOwner();
});

it('invites a cashier who gets a set-your-password email', function () {
    $this->actingAs($this->owner)->post(route('admin.team.store'), ['name' => 'Jessa Reyes', 'email' => 'jessa@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    $cashier = User::where('email', 'jessa@example.com')->sole();
    expect($cashier->role)->toBe(User::ROLE_STAFF)
        ->and($cashier->business_id)->toBe($this->owner->business_id)
        ->and($cashier->email_verified_at)->not->toBeNull();

    Notification::assertSentTo($cashier, StaffInvitation::class);
});

it('lets an invited cashier set a password and land on the register', function () {
    $this->actingAs($this->owner)->post(route('admin.team.store'), ['name' => 'Jessa', 'email' => 'jessa@example.com']);
    $cashier = User::where('email', 'jessa@example.com')->sole();

    $token = null;
    Notification::assertSentTo($cashier, StaffInvitation::class, function (StaffInvitation $invitation) use (&$token) {
        $token = $invitation->token;

        return true;
    });

    auth()->logout();
    $this->post(route('password.store'), ['token' => $token, 'email' => 'jessa@example.com', 'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123'])
        ->assertSessionHasNoErrors();

    $this->post(route('login'), ['email' => 'jessa@example.com', 'password' => 'secret-pass-123']);
    $this->get(route('dashboard'))->assertRedirect(route('pos'));
});

it('enforces the staff limit on the Tindahan plan', function () {
    $this->owner->business->update(['plan' => 'tindahan']);
    User::factory()->count(3)->staffOf($this->owner->business)->create();

    $this->actingAs($this->owner)->post(route('admin.team.store'), ['name' => 'Fourth', 'email' => 'fourth@example.com'])
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'fourth@example.com')->exists())->toBeFalse();
});

it('removes a cashier and keeps their name on past orders', function () {
    $menu = demoMenu($this->owner);
    $cashier = cashierOf($this->owner);
    $this->actingAs($cashier)->postJson(route('pos.orders.store'), orderPayload([[$menu['water500'], 1]], 'gcash'));

    $this->actingAs($this->owner)->delete(route('admin.team.destroy', $cashier))->assertRedirect();

    expect(User::find($cashier->id))->toBeNull()
        ->and(Order::withoutGlobalScopes()->sole()->cashier_name)->toBe($cashier->name);
});

it('cannot remove someone from another shop', function () {
    $stranger = cashierOf(shopOwner());

    $this->actingAs($this->owner)->delete(route('admin.team.destroy', $stranger))->assertNotFound();
    expect(User::find($stranger->id))->not->toBeNull();
});

it('saves cashier permissions', function () {
    $this->actingAs($this->owner)->patch(route('admin.team.permissions'), ['permissions' => [
        'run_audit' => 1, 'view_costs' => 1, 'void_orders' => 0, 'log_expenses' => 0,
    ]])->assertRedirect();

    $business = $this->owner->business->refresh();
    expect($business->cashierCan('view_costs'))->toBeTrue()
        ->and($business->cashierCan('log_expenses'))->toBeFalse();
});
