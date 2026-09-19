<?php

use App\Enums\ItemKind;
use App\Enums\StockMovementReason;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->owner = shopOwner();
    $this->menu = demoMenu($this->owner);
});

it('adds a menu item with sizes and ingredient links', function () {
    $category = Category::withoutGlobalScopes()->create(['business_id' => $this->owner->business_id, 'name' => 'Drinks']);

    $this->actingAs($this->owner)->post(route('admin.inventory.store'), [
        'kind' => 'menu',
        'name' => 'Iced Coffee',
        'category_id' => $category->id,
        'variants' => [
            ['label' => '16oz', 'cost' => 27, 'price' => 79],
            ['label' => '22oz', 'cost' => 34, 'price' => 99],
        ],
        'recipe' => [
            ['piece_item_id' => $this->menu['cup16']->id, 'qty' => 1, 'variant_index' => 0],
            ['piece_item_id' => $this->menu['cup22']->id, 'qty' => 1, 'variant_index' => 1],
        ],
    ])->assertRedirect(route('admin.inventory', ['tab' => 'menu']));

    $item = Item::withoutGlobalScopes()->where('name', 'Iced Coffee')->sole();
    expect($item->kind)->toBe(ItemKind::Menu)
        ->and($item->variants->pluck('label')->all())->toBe(['16oz', '22oz'])
        ->and($item->variants->first()->marginPercent())->toBe(65.8)
        ->and($item->recipeLines->firstWhere('piece_item_id', $this->menu['cup22']->id)->item_variant_id)->toBe($item->variants->last()->id);
});

it('updates sizes in place and removes deleted ones', function () {
    $tea = $this->menu['tea'];

    $this->actingAs($this->owner)->put(route('admin.inventory.update', $tea), [
        'kind' => 'menu',
        'name' => 'Iced Tea',
        'variants' => [['id' => $this->menu['tea16']->id, 'label' => '16oz', 'cost' => 10, 'price' => 50]],
    ])->assertRedirect();

    expect($tea->variants()->count())->toBe(1)
        ->and((float) $this->menu['tea16']->refresh()->price)->toBe(50.0);
});

it('requires a size with a price for menu items', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.inventory.store'), ['kind' => 'menu', 'name' => 'Mystery'])
        ->assertSessionHasErrors('variants');
});

it('requires a unit cost for pieces and bulk items', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.inventory.store'), ['kind' => 'bulk', 'name' => 'Ketchup', 'on_hand' => 5])
        ->assertSessionHasErrors('unit_cost');
});

it('refuses to link a piece from another shop', function () {
    $foreign = demoMenu(shopOwner());

    $this->actingAs($this->owner)->post(route('admin.inventory.store'), [
        'kind' => 'menu',
        'name' => 'Sneaky Burger',
        'variants' => [['label' => 'Regular', 'price' => 99]],
        'recipe' => [['piece_item_id' => $foreign['bun']->id, 'qty' => 1]],
    ])->assertSessionHasErrors('recipe.0.piece_item_id');
});

it('logs an adjustment when the owner changes the on-hand count', function () {
    $this->actingAs($this->owner)->put(route('admin.inventory.update', $this->menu['bun']), [
        'kind' => 'piece', 'name' => 'Burger bun', 'unit' => 'pc', 'unit_cost' => 7.5, 'on_hand' => 150,
    ])->assertRedirect();

    expect((float) $this->menu['bun']->refresh()->on_hand)->toBe(150.0);

    $movement = StockMovement::withoutGlobalScopes()->where('item_id', $this->menu['bun']->id)->sole();
    expect($movement->reason)->toBe(StockMovementReason::Adjustment)->and((float) $movement->qty_change)->toBe(50.0);
});

it('archives an item so it leaves the register, and restores it', function () {
    $this->actingAs($this->owner)->patch(route('admin.inventory.archive', $this->menu['burger']))->assertRedirect();
    expect(collect($this->actingAs($this->owner)->get(route('pos'))->viewData('menu'))->pluck('name'))->not->toContain('Cheeseburger');

    $this->actingAs($this->owner)->patch(route('admin.inventory.restore', $this->menu['burger']));
    expect(collect($this->actingAs($this->owner)->get(route('pos'))->viewData('menu'))->pluck('name'))->toContain('Cheeseburger');
});

it('imports the Excel pricing matrix from CSV', function () {
    $csv = "name,category,size,cost,price\nMilk Tea,Drinks,16oz,22,69\nMilk Tea,Drinks,22oz,28,\"₱89.00\"\nSiomai Rice,Rice meals,,30,75\nBroken row,,,,\n";
    $file = UploadedFile::fake()->createWithContent('menu.csv', $csv);

    $this->actingAs($this->owner)
        ->post(route('admin.inventory.import'), ['file' => $file])
        ->assertRedirect()
        ->assertSessionHas('import_errors', fn ($errors) => count($errors) === 1);

    $milkTea = Item::withoutGlobalScopes()->where('name', 'Milk Tea')->sole();
    expect($milkTea->variants->pluck('price')->map(fn ($price) => (float) $price)->all())->toBe([69.0, 89.0])
        ->and($milkTea->category->name)->toBe('Drinks')
        ->and(Item::withoutGlobalScopes()->where('name', 'Siomai Rice')->sole()->variants->first()->label)->toBe('Regular');
});

it('adds a category inline', function () {
    $this->actingAs($this->owner)
        ->postJson(route('admin.categories.store'), ['name' => 'Desserts'])
        ->assertCreated()
        ->assertJsonPath('category.name', 'Desserts');

    $this->actingAs($this->owner)->postJson(route('admin.categories.store'), ['name' => 'Desserts'])->assertUnprocessable();
});

it('hides recipe links on the Tindahan plan', function () {
    $this->owner->business->update(['plan' => 'tindahan']);

    $this->actingAs($this->owner)->post(route('admin.inventory.store'), [
        'kind' => 'menu',
        'name' => 'Plain Burger',
        'variants' => [['label' => 'Regular', 'price' => 80]],
        'recipe' => [['piece_item_id' => $this->menu['bun']->id, 'qty' => 1]],
    ])->assertRedirect();

    expect(Item::withoutGlobalScopes()->where('name', 'Plain Burger')->sole()->recipeLines)->toBeEmpty();
});
