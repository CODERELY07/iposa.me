<?php

namespace App\Http\Requests\Pos;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
        $enabledMethods = array_map(
            fn (PaymentMethod $method) => $method->value,
            $this->user()->business->enabledPaymentMethods(),
        );

        return [
            'uuid' => ['required', 'uuid'],
            'payment_method' => ['required', Rule::in($enabledMethods)],
            'tendered' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.variant_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one item to the order.',
            'payment_method.in' => 'That payment method is turned off in Settings.',
        ];
    }
}
