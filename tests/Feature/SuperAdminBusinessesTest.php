<?php

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\RegisterBusinessUserService;

beforeEach(function () {
    $this->operator = User::factory()->create(['role' => 'super_admin']);
});

it('lists businesses with their owner and status', function () {
    $business = Business::factory()->active()->create(['business_name' => 'Boba Lab']);

    $this->actingAs($this->operator)
        ->get(route('super_admin.businesses.index'))
        ->assertOk()
        ->assertSee('Boba Lab')
        ->assertSee($business->owner->email)
        ->assertSee('Active');
});

it('filters businesses by status', function () {
    Business::factory()->active()->create(['business_name' => 'Paying Place']);
    Business::factory()->suspended()->create(['business_name' => 'Blocked Place']);

    $this->actingAs($this->operator)
        ->get(route('super_admin.businesses.index', ['status' => BusinessStatus::Suspended->value]))
        ->assertOk()
        ->assertSee('Blocked Place')
        ->assertDontSee('Paying Place');
});

it('ignores an unknown status filter', function () {
    Business::factory()->create(['business_name' => 'Trial Place']);

    $this->actingAs($this->operator)
        ->get(route('super_admin.businesses.index', ['status' => 'bogus']))
        ->assertOk()
        ->assertSee('Trial Place');
});

it('searches by business name and owner email', function () {
    $owner = User::factory()->create(['role' => 'admin', 'email' => 'maria@kapetburger.ph']);
    Business::factory()->for($owner, 'owner')->create(['business_name' => "Kape't Burger"]);
    Business::factory()->create(['business_name' => 'Sisig Station']);

    $this->actingAs($this->operator)
        ->get(route('super_admin.businesses.index', ['q' => 'kapetburger']))
        ->assertOk()
        ->assertSee("Kape't Burger")
        ->assertDontSee('Sisig Station');
});

it('shows an empty state when nothing matches', function () {
    $this->actingAs($this->operator)
        ->get(route('super_admin.businesses.index', ['q' => 'nothing-like-this']))
        ->assertOk()
        ->assertSee('No businesses match these filters.');
});

it('shows one business with its owner and dates', function () {
    $business = Business::factory()->pastDue()->create(['business_name' => 'Milky Way Tea']);

    $this->actingAs($this->operator)
        ->get(route('super_admin.businesses.show', $business))
        ->assertOk()
        ->assertSee('Milky Way Tea')
        ->assertSee($business->owner->name)
        ->assertSee('Past due')
        ->assertSee('Due date passed.');
});

it('returns 404 for a business that does not exist', function () {
    $this->actingAs($this->operator)
        ->get(route('super_admin.businesses.show', 999))
        ->assertNotFound();
});

it('keeps non-operators out of the business pages', function (string $role, string $home) {
    $user = User::factory()->create(['role' => $role]);
    $business = Business::factory()->create();

    $this->actingAs($user)->get(route('super_admin.businesses.index'))->assertRedirect(route($home));
    $this->actingAs($user)->get(route('super_admin.businesses.show', $business))->assertRedirect(route($home));
})->with([
    'business owner' => ['admin', 'admin.dashboard'],
    'cashier' => ['staff', 'pos'],
]);

it('starts a 14-day trial for new sign-ups', function () {
    $this->freezeTime();

    $user = app(RegisterBusinessUserService::class)->register([
        'name' => 'Maria Santos',
        'email' => 'maria@example.com',
        'password' => 'password',
        'business_name' => "Kape't Burger",
        'business_type' => 'Burger & fast food',
    ]);

    $business = $user->business()->first();

    expect($business->status)->toBe(BusinessStatus::Trial)
        ->and($business->start_date->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($business->due_date->toDateTimeString())->toBe(now()->addDays(RegisterBusinessUserService::TRIAL_DAYS)->toDateTimeString());
});
