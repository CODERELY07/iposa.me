<?php

namespace Database\Seeders;

use App\Enums\SubscriptionPaymentStatus;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use App\Services\Pos\CheckoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Other shops on the platform, so the operator console has something to show:
 * trials, paying shops (two gone quiet), past due, one suspended, and payments to review.
 */
class BusinessSeeder extends Seeder
{
    public function __construct(private CheckoutService $checkout) {}

    public function run(): void
    {
        if (Business::query()->count() > 1) {
            return;
        }

        mt_srand(7);
        $operator = User::where('role', User::ROLE_SUPER_ADMIN)->first();

        Business::factory()->count(5)->create()->each(fn (Business $business, int $index) => $this->withSales($business, $index < 3 ? 12 : 0, 0));

        $paying = Business::factory()->count(8)->active()->create();

        $paying->each(function (Business $business, int $index) use ($operator): void {
            // Two shops that stopped selling a few days ago.
            $this->withSales($business, mt_rand(20, 60), $index < 2 ? mt_rand(4, 6) : 0);

            foreach (range(mt_rand(1, 5), 1) as $monthsAgo) {
                $business->subscriptionPayments()->create([
                    'plan' => $business->plan, 'amount' => $business->planDetails()['price'], 'method' => mt_rand(0, 1) ? 'gcash' : 'bank',
                    'reference' => (string) mt_rand(100000000, 999999999), 'status' => SubscriptionPaymentStatus::Paid,
                    'submitted_by' => $business->user_id, 'reviewed_by' => $operator?->id, 'reviewed_at' => now()->subMonths($monthsAgo)->subDays(mt_rand(0, 10)),
                ]);
            }
        });

        Business::factory()->count(2)->pastDue()->create()->each(function (Business $business): void {
            $business->subscriptionPayments()->create([
                'plan' => $business->plan, 'amount' => $business->planDetails()['price'], 'method' => 'gcash',
                'reference' => (string) mt_rand(100000000, 999999999), 'status' => SubscriptionPaymentStatus::Pending,
                'submitted_by' => $business->user_id,
            ]);
        });

        Business::factory()->suspended()->create(['suspension_reason' => 'Chargeback on last payment']);
    }

    /**
     * One menu item and a few days of orders, rung up through the real checkout.
     */
    private function withSales(Business $business, int $orders, int $quietDays): void
    {
        $item = Item::factory()->menu(['Regular' => [mt_rand(60, 150), mt_rand(20, 50)]])->create(['business_id' => $business->id]);
        $variant = $item->variants()->first();
        $owner = $business->owner;

        foreach (range(1, $orders) as $ignored) {
            if ($orders === 0) {
                break;
            }

            $this->checkout->checkout($business, $owner, [
                'uuid' => (string) Str::uuid(),
                'payment_method' => 'gcash',
                'lines' => [['variant_id' => $variant->id, 'qty' => mt_rand(1, 3)]],
            ], now()->subDays(mt_rand($quietDays, $quietDays + 6))->setTime(mt_rand(10, 20), mt_rand(0, 59)));
        }
    }
}
