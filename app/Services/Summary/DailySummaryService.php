<?php

namespace App\Services\Summary;

use App\Models\Business;
use App\Models\DailySummary;
use App\Reports\DailyBrief;
use App\Services\Sms\SmsGateClient;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Texts each opted-in owner their voids and low stock once a day, after their chosen time.
 */
class DailySummaryService
{
    /** A failed send waits this long before the next try. */
    private const RETRY_AFTER_MINUTES = 5;

    public function __construct(private DailyBrief $brief, private SmsGateClient $sms) {}

    /**
     * Send every summary that is due right now. Safe to call every minute.
     *
     * @return int summaries sent
     */
    public function sendDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $sent = 0;

        // Filtered here rather than with a JSON path in SQL, which behaves differently on SQLite and Postgres.
        $businesses = Business::query()->whereNotNull('settings')->get()
            ->filter(fn (Business $business) => $business->smsSummary()['enabled']);

        foreach ($businesses as $business) {
            if ($business->isSuspended() || $business->requiresPayment() || ! $this->isDue($business, $now)) {
                continue;
            }

            if ($this->send($business, $now)->wasSent()) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Whether this shop's text for today still needs sending (or retrying).
     */
    public function isDue(Business $business, CarbonImmutable $now): bool
    {
        $settings = $business->smsSummary();

        if (! $settings['enabled'] || $settings['numbers'] === [] || $now->format('H:i') < $settings['time']) {
            return false;
        }

        $today = $this->todays($business, $now);

        return match (true) {
            $today === null => true,
            $today->wasSent() => false,
            $today->attempts >= DailySummary::MAX_ATTEMPTS => false,
            default => $today->updated_at->lte($now->subMinutes(self::RETRY_AFTER_MINUTES)),
        };
    }

    /**
     * Build and send today's text, recording what happened either way.
     */
    public function send(Business $business, ?CarbonImmutable $now = null): DailySummary
    {
        $now ??= CarbonImmutable::now();
        $summary = $this->todays($business, $now) ?? new DailySummary(['business_id' => $business->id, 'date' => $now->toDateString(), 'attempts' => 0]);

        $summary->body = $this->brief->build($business, $now);
        $summary->attempts++;

        try {
            $this->sms->send($business->smsSummary()['numbers'], $summary->body);
            $summary->fill(['status' => DailySummary::SENT, 'error' => null, 'sent_at' => $now]);
        } catch (RuntimeException $exception) {
            $summary->fill(['status' => DailySummary::FAILED, 'error' => mb_substr($exception->getMessage(), 0, 250)]);
        }

        $summary->save();

        return $summary;
    }

    /**
     * A test text, so the owner knows the phone and number work before relying on it.
     *
     * @throws RuntimeException
     */
    public function sendTest(Business $business): void
    {
        $this->sms->send($business->smsSummary()['numbers'], 'iPOSa test for '.$business->business_name.'. Each evening you will get the voids and low stock here.');
    }

    private function todays(Business $business, CarbonImmutable $now): ?DailySummary
    {
        return DailySummary::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereDate('date', $now->toDateString())
            ->first();
    }
}
