<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCapitalContributionRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:160'],
        ];
    }
}
