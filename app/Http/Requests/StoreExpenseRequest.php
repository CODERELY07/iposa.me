<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)->only(ExpenseCategory::selectable())],
            'description' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'kind' => ['nullable', Rule::enum(ExpenseKind::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['description' => 'what it was for'];
    }
}
