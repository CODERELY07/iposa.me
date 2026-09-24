<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegisterSettingsRequest extends FormRequest
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
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => [Rule::enum(PaymentMethod::class)],
            'audit_reminder_time' => ['required', 'date_format:H:i'],
            'default_low_threshold' => ['required', 'numeric', 'min:0', 'max:99999'],
            'audit_pieces' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['payment_methods.required' => 'Keep at least one payment method on.'];
    }
}
