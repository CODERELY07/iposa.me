<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use App\Enums\ItemKind;
use App\Models\Asset;
use App\Models\Business;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\User;
use App\Services\Audit\ClosingAuditService;
use App\Services\Inventory\StockService;
use App\Services\Pos\CheckoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * "Kape't Burger": a realistic shop with 14 days of sales, closing audits and expenses,
 * all created through the same services the app uses, so every report adds up.
 */
class DemoShopSeeder extends Seeder
{
    private const DAYS = 14;

    public function __construct(
        private CheckoutService $checkout,
        private ClosingAuditService $audits,
        private StockService $stock,
    ) {}

    public function run(): void
    {
        mt_srand(2026);

        $owner = User::where('email', 'admin@gmail.com')->firstOrFail();
        $cashier = User::where('email', 'staff@gmail.com')->first();

        $business = Business::firstOrCreate(['user_id' => $owner->id], [
            'business_name' => "Kape't Burger",
            'business_type' => 'Burger & fast food',
            'plan' => 'negosyo',
            'status' => 'active',
            'start_date' => today()->subDays(self::DAYS + 30),
            'due_date' => today()->addDays(16),
            'address' => 'J.P. Rizal St., Sto. Niño, Marikina City',
            'receipt_footer' => 'Salamat po! Balik kayo',
        ]);

        $owner->forceFill(['business_id' => $business->id])->save();
        $cashier?->forceFill(['business_id' => $business->id])->save();

        if ($business->items()->exists()) {
            return;
        }

        $business->subscriptionPayments()->create([
            'plan' => 'negosyo', 'amount' => 999, 'method' => 'gcash', 'reference' => '1009 482 551 203',
            'status' => 'paid', 'submitted_by' => $owner->id, 'reviewed_at' => today()->subDays(14),
        ]);

        $pieces = $this->seedPieces($business);
        $bulk = $this->seedBulk($business);
        $variants = $this->seedMenu($business, $pieces);

        $this->seedDays($business, [$owner, $cashier ?? $owner], $variants, $bulk);
        $this->seedExpensesAndAssets($business, $owner);

        // Freezer count this morning: a couple of items running low, so the dashboard has something to say.
        $this->stock->setOnHand($pieces['Chicken thigh']->refresh(), 6, $owner->id);
        $this->stock->setOnHand($pieces['Cups 22oz']->refresh(), 30, $owner->id);
    }

    /**
     * @return array<string, Item>
     */
    private function seedPieces(Business $business): array
    {
        $rows = [
            ['Burger bun', 7.50, 700, 40], ['Beef patty', 24.00, 800, 40], ['Cheese slice', 6.00, 700, 60],
            ['Bacon strip', 11.00, 300, 30], ['Chicken thigh', 38.00, 250, 20], ['Egg', 8.50, 400, 30],
            ['Rice (cup)', 6.00, 900, 50], ['Fries portion', 16.00, 700, 40], ['Cups 16oz', 3.20, 900, 100], ['Cups 22oz', 4.10, 900, 100],
        ];

        $pieces = [];

        foreach ($rows as [$name, $cost, $onHand, $threshold]) {
            $pieces[$name] = Item::withoutGlobalScopes()->create([
                'business_id' => $business->id, 'kind' => ItemKind::Piece, 'name' => $name, 'unit' => 'pc',
                'on_hand' => $onHand, 'low_threshold' => $threshold, 'unit_cost' => $cost,
                'created_at' => today()->subDays(self::DAYS + 1),
            ]);
        }

        return $pieces;
    }

    /**
     * @return array<string, array{item: Item, dailyUse: float, restockTo: float}>
     */
    private function seedBulk(Business $business): array
    {
        $rows = [
            ['Cooking oil', '1L bottle', 145.00, 5.0, 0.5, 6.0],
            ['Mayonnaise', '5kg tub', 890.00, 2.0, 0.2, 3.0],
            ['Ketchup', 'squeeze bottle', 68.00, 6.0, 0.75, 8.0],
            ['Iced tea powder', '1kg pack', 280.00, 3.0, 0.3, 4.0],
            ['Coffee beans', '1kg bag', 780.00, 2.0, 0.2, 3.0],
            ['LPG', '11kg tank', 1050.00, 1.5, 0.15, 2.0],
        ];

        $bulk = [];

        foreach ($rows as [$name, $unit, $cost, $onHand, $dailyUse, $restockTo]) {
            $item = Item::withoutGlobalScopes()->create([
                'business_id' => $business->id, 'kind' => ItemKind::Bulk, 'name' => $name, 'unit' => $unit,
                'on_hand' => $onHand, 'low_threshold' => 1, 'unit_cost' => $cost,
                'created_at' => today()->subDays(self::DAYS + 1),
            ]);

            $bulk[$name] = ['item' => $item, 'dailyUse' => $dailyUse, 'restockTo' => $restockTo];
        }

        return $bulk;
    }

    /**
     * @param  array<string, Item>  $pieces
     * @return list<array{variant: ItemVariant, weight: int}>
     */
    private function seedMenu(Business $business, array $pieces): array
    {
        $categories = [];

        foreach ([['Burgers', 'brand'], ['Rice meals', 'rose'], ['Sides', 'yellow'], ['Drinks', 'sky'], ['Add-ons', 'ink']] as $sort => [$name, $color]) {
            $categories[$name] = Category::withoutGlobalScopes()->create(['business_id' => $business->id, 'name' => $name, 'color' => $color, 'sort' => $sort]);
        }

        // [name, category, sizes [label, price, cost, weight], recipe [piece, qty, size label or null]]
        $menu = [
            ['Classic Burger', 'Burgers', [['Regular', 89, 38, 10]], [['Burger bun', 1], ['Beef patty', 1]]],
            ['Cheeseburger', 'Burgers', [['Regular', 109, 46, 16]], [['Burger bun', 1], ['Beef patty', 1], ['Cheese slice', 1]]],
            ['Double Cheese', 'Burgers', [['Regular', 159, 78, 6]], [['Burger bun', 1], ['Beef patty', 2], ['Cheese slice', 2]]],
            ['Bacon Burger', 'Burgers', [['Regular', 149, 68, 5]], [['Burger bun', 1], ['Beef patty', 1], ['Bacon strip', 2]]],
            ['Burger Steak', 'Rice meals', [['w/ rice', 119, 45, 6]], [['Beef patty', 1], ['Rice (cup)', 1]]],
            ['Chicken & Rice', 'Rice meals', [['w/ rice', 129, 58, 7]], [['Chicken thigh', 1], ['Rice (cup)', 1]]],
            ['Tapsilog', 'Rice meals', [['w/ egg', 139, 72, 5]], [['Egg', 1], ['Rice (cup)', 1]]],
            ['Fries', 'Sides', [['Regular', 59, 18, 9], ['Large', 89, 26, 6]], [['Fries portion', 1, 'Regular'], ['Fries portion', 1.5, 'Large']]],
            ['Iced Tea', 'Drinks', [['16oz', 45, 9.50, 8], ['22oz', 60, 13, 9]], [['Cups 16oz', 1, '16oz'], ['Cups 22oz', 1, '22oz']]],
            ['Iced Coffee', 'Drinks', [['16oz', 79, 27, 5], ['22oz', 99, 34, 4]], [['Cups 16oz', 1, '16oz'], ['Cups 22oz', 1, '22oz']]],
            ['Bottled Water', 'Drinks', [['500ml', 25, 11, 3]], []],
            ['Extra Cheese', 'Add-ons', [['1 slice', 15, 6, 3]], [['Cheese slice', 1]]],
            ['Fried Egg', 'Add-ons', [['1 pc', 20, 8.50, 2]], [['Egg', 1]]],
        ];

        $weighted = [];

        foreach ($menu as [$name, $category, $sizes, $recipe]) {
            $item = Item::withoutGlobalScopes()->create([
                'business_id' => $business->id, 'kind' => ItemKind::Menu, 'name' => $name, 'category_id' => $categories[$category]->id,
                'on_hand' => $recipe === [] ? 120 : null, 'low_threshold' => $recipe === [] ? 12 : null,
                'created_at' => today()->subDays(self::DAYS + 1),
            ]);

            $variantsByLabel = [];

            foreach ($sizes as $sort => [$label, $price, $cost, $weight]) {
                $variant = $item->variants()->create(['label' => $label, 'price' => $price, 'cost' => $cost, 'sort' => $sort]);
                $variantsByLabel[$label] = $variant;
                $weighted[] = ['variant' => $variant->setRelation('item', $item), 'weight' => $weight];
            }

            foreach ($recipe as $line) {
                $item->recipeLines()->create([
                    'piece_item_id' => $pieces[$line[0]]->id,
                    'qty' => $line[1],
                    'item_variant_id' => isset($line[2]) ? $variantsByLabel[$line[2]]->id : null,
                ]);
            }
        }

        return $weighted;
    }

    /**
     * Sales every day, a closing audit every night except one skipped day, today still open.
     *
     * @param  list<User>  $cashiers
     * @param  list<array{variant: ItemVariant, weight: int}>  $variants
     * @param  array<string, array{item: Item, dailyUse: float, restockTo: float}>  $bulk
     */
    private function seedDays(Business $business, array $cashiers, array $variants, array $bulk): void
    {
        $totalWeight = array_sum(array_column($variants, 'weight'));

        for ($daysAgo = self::DAYS - 1; $daysAgo >= 0; $daysAgo--) {
            $day = today()->subDays($daysAgo);
            $isToday = $daysAgo === 0;
            $closingTime = $day->copy()->setTime(21, 0);
            $orderCount = mt_rand(28, 40) + ($day->isWeekend() ? 14 : 0) + ($day->isFriday() ? 6 : 0);

            $times = collect(range(1, $orderCount))
                ->map(fn () => $day->copy()->setTime(10, 0)->addMinutes(mt_rand(0, 660)))
                ->filter(fn (Carbon $time) => ! $isToday || $time->lt(now()))
                ->sort()
                ->values();

            foreach ($times as $paidAt) {
                $lines = [];

                foreach (range(1, mt_rand(1, 3)) as $ignored) {
                    $variant = $this->pick($variants, $totalWeight);
                    $lines[] = ['variant_id' => $variant->id, 'qty' => mt_rand(1, 10) > 8 ? 2 : 1];
                }

                $total = collect($lines)->sum(fn ($line) => (float) collect($variants)->first(fn ($entry) => $entry['variant']->id === $line['variant_id'])['variant']->price * $line['qty']);
                $method = $this->pickPayment();

                $this->checkout->checkout($business, $cashiers[mt_rand(0, count($cashiers) - 1)], [
                    'uuid' => (string) Str::uuid(),
                    'payment_method' => $method,
                    'tendered' => $method === 'cash' ? ceil($total / 100) * 100 : null,
                    'lines' => $lines,
                ], $paidAt);
            }

            // Tonight's count. Skip one night early on (real life), and today stays open.
            if ($isToday || $daysAgo === 9) {
                continue;
            }

            $counts = [];

            foreach ($bulk as $entry) {
                $item = $entry['item']->refresh();
                $used = round($entry['dailyUse'] * (mt_rand(70, 130) / 100) * ($day->isWeekend() ? 1.3 : 1) * 4) / 4;
                $counted = max(0, (float) $item->on_hand - max(0.25, $used));

                if ($counted < 1) {
                    $counted += $entry['restockTo'];
                }

                $counts[$item->id] = $counted;
            }

            $auditor = $cashiers[count($cashiers) - 1];
            $submittedAt = $closingTime->copy()->addMinutes(mt_rand(20, 45));

            $this->audits->submit($business, $auditor, $counts, $submittedAt->copy()->subSeconds(mt_rand(40, 75)), $day, $submittedAt);
        }
    }

    private function seedExpensesAndAssets(Business $business, User $owner): void
    {
        $from = today()->subDays(self::DAYS - 1);
        $log = function (Carbon $date, ExpenseCategory $category, string $description, float $amount) use ($business, $owner): void {
            Expense::withoutGlobalScopes()->create([
                'business_id' => $business->id, 'date' => $date->toDateString(), 'category' => $category, 'description' => $description,
                'kind' => $category->defaultKind(), 'amount' => $amount, 'user_id' => $owner->id, 'logged_by' => $owner->name,
            ]);
        };

        for ($day = $from->copy(); $day->lte(today()); $day->addDay()) {
            if ($day->day === 1) {
                $log($day, ExpenseCategory::Rent, 'Stall rent · '.$day->format('F'), 18000);
            }

            if ($day->day === 12) {
                $log($day, ExpenseCategory::Utilities, 'PLDT fiber', 1699);
            }

            if ($day->day === 15) {
                $log($day, ExpenseCategory::Utilities, 'Meralco · '.$day->copy()->subMonth()->format('F'), 6840);
                $log($day, ExpenseCategory::Wages, 'Payroll · 3 staff, 1st half', 16500);
            }

            if ($day->isLastOfMonth()) {
                $log($day, ExpenseCategory::Wages, 'Payroll · 3 staff, 2nd half', 16500);
            }

            if (mt_rand(1, 10) <= 7) {
                $log($day, ExpenseCategory::Supplies, 'Ice, '.mt_rand(2, 4).' sacks', mt_rand(2, 4) * 100);
            }

            if ($day->isSaturday()) {
                $log($day, ExpenseCategory::Wages, 'Part-timer, weekend', 500);
            }
        }

        $log(today()->subDays(6), ExpenseCategory::StockPurchase, 'Buns ×200 · Pan de Manila', 1500);
        $log(today()->subDays(3), ExpenseCategory::Misc, 'Paper bags, tissue', 420);

        $assets = [
            ['Espresso machine', 'Wellcraft PH', 85000, 7083.33, 12, 6, 6],
            ['Chest freezer 9 cu.ft.', 'Abenson', 24990, 4165, 6, 4, 4],
            ['Blender ×2', 'Home Credit', 9800, 1633.33, 6, 2, 2],
            ['Commercial griddle', 'Cash', 12500, null, 1, 1, 8],
        ];

        foreach ($assets as [$name, $vendor, $price, $installment, $terms, $paid, $monthsAgo]) {
            Asset::withoutGlobalScopes()->create([
                'business_id' => $business->id, 'name' => $name, 'vendor' => $vendor, 'price' => $price,
                'installment_amount' => $installment, 'terms' => $terms, 'paid_count' => $paid,
                'first_due_on' => today()->subMonthsNoOverflow($monthsAgo)->addDays(5)->toDateString(),
            ]);
        }

        // This month's espresso machine installment, paid last week.
        $espresso = Asset::withoutGlobalScopes()->where('business_id', $business->id)->where('name', 'Espresso machine')->first();
        Expense::withoutGlobalScopes()->create([
            'business_id' => $business->id, 'date' => today()->subDays(5)->toDateString(), 'category' => ExpenseCategory::Payables,
            'description' => 'Espresso machine · installment 7 of 12', 'kind' => ExpenseKind::Fixed, 'amount' => 7083.33,
            'asset_id' => $espresso->id, 'user_id' => $owner->id, 'logged_by' => $owner->name,
        ]);
        $espresso->increment('paid_count');
    }

    /**
     * @param  list<array{variant: ItemVariant, weight: int}>  $variants
     */
    private function pick(array $variants, int $totalWeight): ItemVariant
    {
        $roll = mt_rand(1, $totalWeight);

        foreach ($variants as $entry) {
            $roll -= $entry['weight'];

            if ($roll <= 0) {
                return $entry['variant'];
            }
        }

        return $variants[0]['variant'];
    }

    private function pickPayment(): string
    {
        $roll = mt_rand(1, 10);

        return $roll <= 6 ? 'cash' : ($roll <= 9 ? 'gcash' : 'maya');
    }
}
