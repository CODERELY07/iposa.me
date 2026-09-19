<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'terms' => ['required', 'integer', 'min:1', 'max:60'],
            'installment_amount' => ['nullable', 'required_unless:terms,1', 'numeric', 'gt:0', 'max:99999999'],
            'first_due_on' => ['required', 'date'],
            'paid_count' => ['nullable', 'integer', 'min:0', 'lte:terms'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'installment_amount.required_unless' => 'Enter the monthly amount for installment purchases.',
        ];
    }
}
