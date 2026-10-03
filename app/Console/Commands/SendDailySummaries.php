<?php

namespace App\Console\Commands;

use App\Services\Summary\DailySummaryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('summary:send')]
#[Description('Text each opted-in owner their voids and low stock, once a day after their chosen time')]
class SendDailySummaries extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DailySummaryService $summaries): int
    {
        $count = $summaries->sendDue();

        if ($count > 0) {
            $this->info("{$count} summary text(s) sent.");
        }

        return self::SUCCESS;
    }
}
