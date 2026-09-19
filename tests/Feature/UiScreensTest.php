<?php

use App\Models\User;

it('shows the public landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('before you lock up.');
});

it('sends each role to its home screen from the dashboard route', function (string $role, string $expectedRoute) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($expectedRoute));
})->with([
    'super admin' => ['super_admin', 'super_admin.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
    'staff' => ['staff', 'pos'],
]);

it('renders every screen a role is allowed to open', function (string $role, array $routes) {
    $user = User::factory()->create(['role' => $role]);

    foreach ($routes as $name => $parameters) {
        $this->actingAs($user)->get(route($name, $parameters))->assertOk();
    }
})->with([
    'staff' => ['staff', ['pos' => [], 'audit' => [], 'staff.orders' => []]],
    'admin' => ['admin', [
        'admin.dashboard' => [],
        'pos' => [],
        'audit' => [],
        'admin.inventory' => [],
        'admin.inventory.create' => [],
        'admin.inventory.edit' => ['item' => 2],
        'admin.expenses' => [],
        'admin.reports' => [],
        'admin.team' => [],
        'admin.settings' => [],
    ]],
    'super admin' => ['super_admin', [
        'super_admin.dashboard' => [],
        'super_admin.tenants' => [],
        'super_admin.tenants.show' => ['tenant' => 128],
        'super_admin.plans' => [],
    ]],
]);

it('keeps staff out of owner screens', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->get(route('admin.reports'))
        ->assertRedirect(route('pos'));
});

it('keeps business owners out of the platform console', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('super_admin.tenants'))
        ->assertRedirect(route('admin.dashboard'));
});

it('hides cost totals from cashiers during the closing audit', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->get(route('audit'))
        ->assertOk()
        ->assertDontSee('Bulk used today');
});
