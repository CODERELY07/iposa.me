<?php

namespace App\Http\Requests\Admin;

use App\Services\Sms\SmsGateClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessProfileRequest extends FormRequest
{
    /**
     * Business types offered at sign-up and in Settings.
     *
     * @var list<string>
     */
    public const BUSINESS_TYPES = [
        'Café / coffee shop',
        'Burger & fast food',
        'Milk tea & drinks',
        'Carinderia / eatery',
        'Bakery',
        'Other food business',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(self::BUSINESS_TYPES)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && SmsGateClient::normalizeNumber((string) $value) === null) {
                    $fail('Enter a Philippine mobile number like 0917 123 4567.');
                }
            }],
            'tin' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\- ]+$/'],
            'receipt_footer' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['tin.regex' => 'TIN can only have numbers and dashes.'];
    }

    /**
     * The validated profile, with the mobile number stored as +639XXXXXXXXX.
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $profile = $this->validated();
        $profile['phone'] = filled($profile['phone'] ?? null) ? SmsGateClient::normalizeNumber((string) $profile['phone']) : null;

        return $profile;
    }
}
