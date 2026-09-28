<?php

use App\Models\Asset;
use App\Models\Audit;
use App\Models\Expense;
use App\Models\Order;
use App\Models\StockMovement;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
    $this->business = $this->owner->business;
});

function countsForAudit(array $counts): array
{
    return ['counts' => collect($counts)->map(fn ($counted, $itemId) => ['item_id' => $itemId, 'counted' => $counted])->values()->all()];
}

it('permanently resets today\'s sales, stock, closing audit and expenses', function () {
    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 2]], 'gcash'))->assertCreated();

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(98.0)
        ->and((float) $this->menu['patty']->refresh()->on_hand)->toBe(98.0)
        ->and($this->business->refresh()->last_order_number)->toBe(1);

    $this->actingAs($this->owner)->postJson(route('audit.store'), countsForAudit([$this->menu['oil']->id => 4]))->assertOk();
    expect((float) $this->menu['oil']->refresh()->on_hand)->toBe(4.0);

    $this->actingAs($this->owner)->post(route('expenses.store'), [
        'date' => today()->toDateString(), 'category' => 'supplies', 'description' => 'Ice', 'amount' => 300,
    ])->assertRedirect();

    $asset = Asset::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'name' => 'Fryer', 'price' => 12000, 'terms' => 1, 'paid_count' => 1, 'first_due_on' => today(),
    ]);
    Expense::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'date' => today(), 'category' => 'payables', 'description' => 'Fryer',
        'kind' => 'fixed', 'amount' => 12000, 'asset_id' => $asset->id, 'logged_by' => $this->owner->name,
    ]);

    $this->actingAs($this->owner)->post(route('admin.reset-today'), [
        'confirmation' => $this->business->business_name,
    ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, '1 order removed')
            && str_contains($status, 'stock restored for')
            && str_contains($status, 'closing audit removed')
            && str_contains($status, '2 expenses removed'));

    expect(Order::withoutGlobalScopes()->count())->toBe(0)
        ->and(Audit::withoutGlobalScopes()->count())->toBe(0)
        ->and(Expense::withoutGlobalScopes()->count())->toBe(0)
        ->and(StockMovement::withoutGlobalScopes()->whereDate('created_at', today())->count())->toBe(0)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(100.0)
        ->and((float) $this->menu['patty']->refresh()->on_hand)->toBe(100.0)
        ->and((float) $this->menu['oil']->refresh()->on_hand)->toBe(5.0)
        ->and($asset->refresh()->paid_count)->toBe(0)
        ->and($this->business->refresh()->last_order_number)->toBe(0);
});

it('refuses to reset without typing the shop\'s name exactly', function () {
    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), orderPayload([[$this->menu['burgerRegular'], 1]], 'gcash'))->assertCreated();

    $this->actingAs($this->owner)->post(route('admin.reset-today'), ['confirmation' => 'not the shop name'])
        ->assertSessionHasErrors('confirmation');

    expect(Order::withoutGlobalScopes()->count())->toBe(1)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(99.0);
});

it('never touches a previous day\'s orders, stock or expenses', function () {
    $yesterday = orderPayload([[$this->menu['burgerRegular'], 1]], 'gcash') + ['offline_created_at' => today()->subDay()->setTime(12, 0)->toIso8601String()];
    $this->actingAs($this->owner)->postJson(route('pos.orders.store'), $yesterday)->assertCreated();

    Expense::withoutGlobalScopes()->create([
        'business_id' => $this->business->id, 'date' => today()->subDay(), 'category' => 'rent', 'description' => 'Rent',
        'kind' => 'fixed', 'amount' => 18000, 'logged_by' => $this->owner->name,
    ]);

    $this->actingAs($this->owner)->post(route('admin.reset-today'), ['confirmation' => $this->business->business_name])
        ->assertRedirect(route('admin.dashboard'));

    expect(Order::withoutGlobalScopes()->count())->toBe(1)
        ->and(Expense::withoutGlobalScopes()->count())->toBe(1)
        ->and((float) $this->menu['bun']->refresh()->on_hand)->toBe(99.0);
});
