<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends a text through SMS Gateway for Android (sms-gate.app): an Android phone with
 * the app installed does the actual sending, so there is no per-message provider fee.
 */
class SmsGateClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.sms_gate.url'))
            && filled(config('services.sms_gate.username'))
            && filled(config('services.sms_gate.password'));
    }

    /**
     * Normalise a Philippine mobile number to +639XXXXXXXXX, or null when it isn't one.
     * Accepts 09171234567, 639171234567, +639171234567 and spaces or dashes in between.
     */
    public static function normalizeNumber(string $number): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', $number);

        if (preg_match('/^(?:\+?63|0)(9\d{9})$/', (string) $digits, $match) !== 1) {
            return null;
        }

        return '+63'.$match[1];
    }

    /**
     * @param  list<string>  $numbers  already normalised
     *
     * @throws RuntimeException when the gateway is unreachable or refuses the message
     */
    public function send(array $numbers, string $text): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('The SMS gateway is not set up on the server (SMS_GATE_USERNAME / SMS_GATE_PASSWORD).');
        }

        try {
            $response = Http::withBasicAuth((string) config('services.sms_gate.username'), (string) config('services.sms_gate.password'))
                ->acceptJson()
                ->timeout(15)
                ->post((string) config('services.sms_gate.url'), [
                    'textMessage' => ['text' => $text],
                    'phoneNumbers' => array_values($numbers),
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach the SMS gateway phone.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('The SMS gateway refused the message (HTTP '.$response->status().').');
        }
    }
}
