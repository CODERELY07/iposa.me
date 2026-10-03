<?php

namespace App\Http\Requests\Admin;

use App\Services\Sms\SmsGateClient;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The end-of-day SMS: whether it is on, when it goes out, and which phones get it.
 */
class UpdateSmsSummaryRequest extends FormRequest
{
    public const MAX_NUMBERS = 3;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sms_enabled' => ['nullable', 'boolean'],
            'sms_time' => ['required', 'date_format:H:i'],
            'sms_numbers' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['sms_time.required' => 'Pick the time the text should go out.'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $raw = $this->rawNumbers();
                $numbers = $this->numbers();

                if (count($numbers) !== count($raw)) {
                    $validator->errors()->add('sms_numbers', 'Use Philippine mobile numbers like 0917 123 4567, separated by commas.');
                } elseif (count($numbers) > self::MAX_NUMBERS) {
                    $validator->errors()->add('sms_numbers', 'Up to '.self::MAX_NUMBERS.' numbers.');
                } elseif ($this->boolean('sms_enabled') && $numbers === []) {
                    $validator->errors()->add('sms_numbers', 'Add the number that should get the text.');
                }
            },
        ];
    }

    /**
     * The numbers typed, normalised to +639XXXXXXXXX, without duplicates.
     *
     * @return list<string>
     */
    public function numbers(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $number) => SmsGateClient::normalizeNumber($number),
            $this->rawNumbers(),
        ))));
    }

    /**
     * @return list<string>
     */
    private function rawNumbers(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) $this->input('sms_numbers')) ?: [])));
    }
}
