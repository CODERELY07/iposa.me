<?php

use App\Models\Business;
use App\Models\User;

it('shows the public landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('before you lock up.');
});

it('sends each role to its home screen from the dashboard route', function () {
    $owner = shopOwner();

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard'))->assertRedirect(route('super_admin.dashboard'));
    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    $this->actingAs(cashierOf($owner))->get(route('dashboard'))->assertRedirect(route('pos'));
});

it('renders every owner screen with real data', function () {
    $owner = shopOwner();
    $menu = demoMenu($owner);

    $this->actingAs($owner)->postJson(route('pos.orders.store'), orderPayload([[$menu['burgerRegular'], 2]]))->assertCreated();

    foreach ([
        route('admin.dashboard'), route('pos'), route('audit'), route('admin.inventory'),
        route('admin.inventory', ['tab' => 'bulk']), route('admin.inventory.create'),
        route('admin.inventory.edit', $menu['burger']), route('admin.expenses'), route('admin.reports'),
        route('admin.reports', ['period' => 'week']), route('admin.team'), route('admin.settings'), route('profile.edit'),
    ] as $url) {
        $this->actingAs($owner)->get($url)->assertOk();
    }
});

it('renders every cashier screen', function () {
    $owner = shopOwner();
    demoMenu($owner);
    $cashier = cashierOf($owner);

    foreach ([route('pos'), route('audit'), route('staff.orders')] as $url) {
        $this->actingAs($cashier)->get($url)->assertOk();
    }
});

it('renders every platform screen', function () {
    $operator = User::factory()->superAdmin()->create();
    $business = shopOwner()->business;

    foreach ([route('super_admin.dashboard'), route('super_admin.businesses.index'), route('super_admin.businesses.show', $business), route('super_admin.plans')] as $url) {
        $this->actingAs($operator)->get($url)->assertOk();
    }
});

it('shows friendly empty states for a brand-new shop', function () {
    $owner = shopOwner();

    $this->actingAs($owner)->get(route('pos'))->assertOk()->assertSee('No menu items yet');
    $this->actingAs($owner)->get(route('audit'))->assertOk()->assertSee('Nothing to count yet');
    $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->assertSee('Setup');
});

it('keeps cashiers out of owner screens', function () {
    $cashier = cashierOf(shopOwner());

    $this->actingAs($cashier)->get(route('admin.reports'))->assertRedirect(route('pos'));
    $this->actingAs($cashier)->get(route('admin.inventory'))->assertRedirect(route('pos'));
});

it('keeps business owners out of the platform console', function () {
    $this->actingAs(shopOwner())
        ->get(route('super_admin.businesses.index'))
        ->assertRedirect(route('admin.dashboard'));
});

it('blocks shop screens for a user with no business', function () {
    $orphan = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($orphan)->get(route('admin.dashboard'))->assertForbidden();
});

it('hides cost totals from cashiers during the closing audit', function () {
    $owner = shopOwner();
    demoMenu($owner);

    $this->actingAs(cashierOf($owner))->get(route('audit'))->assertOk()->assertDontSee('Bulk used today');
    $this->actingAs($owner)->get(route('audit'))->assertOk()->assertSee('Bulk used today');
});

it('shows the real shop name in the sidebar', function () {
    $owner = shopOwner(['business_name' => 'Sizzle Stop']);

    $this->actingAs($owner)->get(route('admin.team'))->assertSee('Sizzle Stop');
    expect(Business::count())->toBe(1);
});
