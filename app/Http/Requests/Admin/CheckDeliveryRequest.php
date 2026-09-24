<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CheckDeliveryRequest extends FormRequest
{
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
            'receipt_quantity' => ['required', 'numeric', 'min:0', 'max:999999'],
            'paid' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'log_expense' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_quantity.required' => 'Enter how many the receipt says were bought.',
        ];
    }

    public function paid(): ?float
    {
        $paid = $this->validated('paid');

        return $paid === null || $paid === '' ? null : (float) $paid;
    }
}
