<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveCashFloatRequest extends FormRequest
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
            'starting_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'counted_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }
}
