<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringExpenseRequest extends FormRequest
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
            'category' => ['required', Rule::enum(ExpenseCategory::class)->only(ExpenseCategory::selectable())],
            'description' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'frequency' => ['required', Rule::enum(ExpenseFrequency::class)],
            'next_due_on' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['description' => 'what it was for', 'next_due_on' => 'first due date'];
    }
}
