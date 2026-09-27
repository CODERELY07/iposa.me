<?php

use App\Exports\DatabaseBackup;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * The platform operator's "Backup database": every shop's rows as INSERT
 * statements, to paste into the SQL editor of a freshly migrated database.
 */
beforeEach(function (): void {
    $this->operator = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'business_id' => null]);
});

function backupText(): string
{
    // false: the generator restarts its keys, so keep the order instead of the keys.
    return implode("\n", iterator_to_array(app(DatabaseBackup::class)->lines(), false));
}

it('downloads a .sql file only the operator can get', function (): void {
    $owner = shopOwner(['business_name' => 'Kapet Burger']);

    $response = actingAs($this->operator)->get(route('super_admin.backup'))->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/sql')
        ->and($response->headers->get('content-disposition'))->toContain('iposa-backup-');

    $sql = $response->streamedContent();

    expect($sql)->toContain('INSERT INTO')
        ->and($sql)->toContain('Kapet Burger')
        ->and($sql)->toContain($owner->email);

    actingAs($owner)->get(route('super_admin.backup'))->assertRedirect(route('admin.dashboard'));

    auth()->logout();
    $this->get(route('super_admin.backup'))->assertRedirect(route('login'));
});

it('restores every row when the file is run back into an empty database', function (): void {
    $owner = shopOwner(['business_name' => "Kape't Burger"]);
    $cashier = cashierOf($owner);
    $menu = demoMenu($owner);

    actingAs($cashier)->postJson(route('pos.orders.store'), [
        'uuid' => (string) Str::uuid(),
        'payment_method' => 'cash',
        'tendered' => 500,
        'lines' => [['variant_id' => $menu['burgerRegular']->id, 'qty' => 2]],
    ])->assertCreated();

    $tables = app(DatabaseBackup::class)->tables();
    $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

    $sql = backupText();

    // Empty every table, then run the backup exactly as the SQL editor would.
    Schema::withoutForeignKeyConstraints(function () use ($tables): void {
        foreach (array_reverse($tables) as $table) {
            DB::table($table)->delete();
        }
    });

    expect(DB::table('businesses')->count())->toBe(0);

    // The test itself runs inside a transaction, so drop the file's own BEGIN/COMMIT.
    DB::unprepared(str_replace(['BEGIN;
', 'COMMIT;
'], '', $sql));

    $after = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

    expect($after->all())->toBe($before->all())
        ->and($before['orders'])->toBeGreaterThan(0);

    // The rows came back as themselves, not as copies with new ids.
    $business = Business::withoutGlobalScopes()->first();
    $burger = Item::withoutGlobalScopes()->where('name', 'Cheeseburger')->first();

    expect($business->business_name)->toBe("Kape't Burger")
        ->and($business->id)->toBe($owner->business_id)
        ->and($burger->id)->toBe($menu['burger']->id)
        ->and((float) Item::withoutGlobalScopes()->find($menu['bun']->id)->on_hand)->toBe((float) $menu['bun']->refresh()->on_hand);
});

it('leaves out logins, cache and queued jobs', function (): void {
    shopOwner();
    actingAs($this->operator)->get(route('super_admin.dashboard'))->assertOk();

    $sql = backupText();

    foreach (DatabaseBackup::SKIPPED as $table) {
        expect($sql)->not->toContain("INSERT INTO \"{$table}\"")
            ->and($sql)->not->toContain("-- {$table}\n");
    }

    expect($sql)->not->toContain('INSERT INTO "migrations"');
});

it('writes parents before children and guards the foreign keys', function (): void {
    shopOwner();

    $sql = backupText();
    $tables = app(DatabaseBackup::class)->tables();

    expect(strpos($sql, '-- businesses'))->toBeLessThan(strpos($sql, '-- items') ?: PHP_INT_MAX)
        ->and(array_search('users', $tables, true))->toBeLessThan(array_search('businesses', $tables, true))
        ->and($sql)->toContain('BEGIN;')
        ->and($sql)->toContain('COMMIT;')
        // No "switch the foreign keys off": Supabase's SQL editor would refuse that.
        ->and($sql)->not->toContain('session_replication_role')
        ->and($sql)->toContain('-- Put each account back in its shop.')
        ->and($sql)->toContain('To restore: run `php artisan migrate`');
});

it('writes nulls, numbers and quotes safely', function (): void {
    $owner = shopOwner(['business_name' => "Mang O'Brien's \"Best\" Shop", 'address' => null]);
    Item::withoutGlobalScopes()->create([
        'business_id' => $owner->business_id, 'kind' => 'bulk', 'name' => 'Oil; DROP TABLE items;--',
        'unit' => 'ml', 'on_hand' => 2838.75, 'unit_cost' => 0.011889,
    ]);

    $sql = backupText();

    expect($sql)->toContain('NULL')
        ->and($sql)->toContain('0.011889');

    $tables = app(DatabaseBackup::class)->tables();

    Schema::withoutForeignKeyConstraints(function () use ($tables): void {
        foreach (array_reverse($tables) as $table) {
            DB::table($table)->delete();
        }
    });

    // The test itself runs inside a transaction, so drop the file's own BEGIN/COMMIT.
    DB::unprepared(str_replace(['BEGIN;
', 'COMMIT;
'], '', $sql));

    expect(Business::withoutGlobalScopes()->first()->business_name)->toBe("Mang O'Brien's \"Best\" Shop")
        ->and(Item::withoutGlobalScopes()->where('kind', 'bulk')->first()->name)->toBe('Oil; DROP TABLE items;--')
        ->and(Schema::hasTable('items'))->toBeTrue();
});
