<?php

namespace App\Http\Requests\Admin;

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
}
