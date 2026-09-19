<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('businesses:mark-overdue')]
#[Description('Move trials and subscriptions past their due date to past due')]
class MarkOverdueBusinesses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->markOverdueBusinesses();

        $this->info("{$count} business(es) marked past due.");

        return self::SUCCESS;
    }
}
