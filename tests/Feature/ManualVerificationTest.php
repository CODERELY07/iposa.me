<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * Point mail at a closed local port so every send really fails, like SMTP blocked on the host.
 */
function breakMail(): void
{
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
        'mail.mailers.smtp.timeout' => 1,
    ]);
}

it('still signs the owner up when the verification email fails', function () {
    breakMail();

    $this->post('/register', [
        'name' => 'Maria Santos', 'email' => 'maria@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        'business_name' => "Kape't Burger", 'business_type' => 'Burger & fast food',
    ])->assertRedirect(route('verification.notice'))->assertSessionHas('status', 'verification-link-failed');

    $this->assertAuthenticated();
    expect(User::where('email', 'maria@example.com')->sole()->business)->not->toBeNull();

    $this->get(route('verification.notice'))->assertOk()->assertSee("We couldn't send the email right now.", false)->assertSee('Ask an agent to verify me');
});

it('tells the user when resending the email fails', function () {
    breakMail();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status', 'verification-link-failed');
});

it('lets an unverified user ask an agent, once', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.request-agent'))->assertSessionHas('status', 'manual-verification-requested');

    expect($user->refresh()->verification_requested_at)->not->toBeNull();
    $this->actingAs($user)->get(route('verification.notice'))->assertSee('Verification requested.')->assertDontSee('Ask an agent to verify me');
});

it('lists requests first for the platform operator and verifies with one button', function () {
    Event::fake([Verified::class]);
    $operator = User::factory()->superAdmin()->create();
    $waiting = User::factory()->unverified()->create(['email' => 'waiting@example.com', 'verification_requested_at' => now()]);
    User::factory()->unverified()->create(['email' => 'quiet@example.com']);

    $this->actingAs($operator)->get(route('super_admin.verifications'))
        ->assertOk()
        ->assertSeeInOrder(['waiting@example.com', 'quiet@example.com'])
        ->assertViewHas('requestedCount', 1);

    $this->actingAs($operator)->post(route('super_admin.users.verify', $waiting))->assertRedirect()->assertSessionHas('status');

    $waiting->refresh();
    expect($waiting->hasVerifiedEmail())->toBeTrue()->and($waiting->verification_requested_at)->toBeNull();
    Event::assertDispatched(Verified::class);
});

it('opens the app for someone the operator verified', function () {
    $owner = User::factory()->unverified()->create(['role' => User::ROLE_ADMIN]);
    $owner->forceFill(['business_id' => Business::factory()->for($owner, 'owner')->create()->id])->save();

    $this->actingAs($owner)->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->superAdmin()->create())->post(route('super_admin.users.verify', $owner));

    $this->actingAs($owner->fresh())->get(route('admin.dashboard'))->assertOk();
});

it('keeps shop owners out of the verification tools', function () {
    $owner = shopOwner();
    $stranger = User::factory()->unverified()->create();

    $this->actingAs($owner)->get(route('super_admin.verifications'))->assertRedirect(route('admin.dashboard'));
    $this->actingAs($owner)->post(route('super_admin.users.verify', $stranger))->assertRedirect(route('admin.dashboard'));

    expect($stranger->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('shows a contact-an-agent message when the reset email fails', function () {
    breakMail();
    config(['iposa.support.email' => 'help@iposa.me']);
    User::factory()->create(['email' => 'maria@example.com']);

    $this->post(route('password.email'), ['email' => 'maria@example.com'])
        ->assertSessionHasErrors(['email' => 'We couldn’t send the reset email right now. Please contact an iPOSa agent at help@iposa.me to get back into your account.']);
});

it('adds a cashier even when the invite email fails, and lets the owner set a password', function () {
    breakMail();
    $owner = shopOwner();

    $this->actingAs($owner)->post(route('admin.team.store'), ['name' => 'Jessa', 'email' => 'jessa@example.com'])
        ->assertSessionHasErrors('email');

    $cashier = User::where('email', 'jessa@example.com')->sole();

    $this->actingAs($owner)->patch(route('admin.team.password', $cashier), ['password' => 'counter-pass-1'])
        ->assertSessionHas('status');

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => 'jessa@example.com', 'password' => 'counter-pass-1'])->assertRedirect();
    $this->assertAuthenticatedAs($cashier->fresh());
});

it('adds a cashier with a password and sends no email', function () {
    Notification::fake();
    $owner = shopOwner();

    $this->actingAs($owner)->post(route('admin.team.store'), ['name' => 'Paolo', 'email' => 'paolo@example.com', 'password' => 'counter-pass-2'])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
    expect(Hash::check('counter-pass-2', User::where('email', 'paolo@example.com')->sole()->password))->toBeTrue();
});

it('cannot set the password of another shop\'s cashier', function () {
    $stranger = cashierOf(shopOwner());

    $this->actingAs(shopOwner())->patch(route('admin.team.password', $stranger), ['password' => 'counter-pass-3'])->assertNotFound();
});
