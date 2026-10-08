<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAuditRequest extends FormRequest
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
            // Made up by the page for a count saved offline, so a replay doesn't close the day twice.
            'uuid' => ['nullable', 'uuid'],
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            // When the shelf was counted, for a count saved offline and sent later.
            'counted_at' => ['nullable', 'date', 'after:-3 days', 'before:+10 minutes'],
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.item_id' => ['required', 'integer', 'distinct'],
            'counts.*.counted' => ['required', 'numeric', 'min:0', 'max:9999999'],
            // Counted more than the system expects: bought more, or recipes deduct too much.
            'counts.*.surplus' => ['nullable', 'in:restock,recipe'],
        ];
    }

    /**
     * Counts as item id => counted.
     *
     * @return array<int, float>
     */
    public function counts(): array
    {
        return collect($this->validated('counts'))
            ->mapWithKeys(fn (array $count) => [(int) $count['item_id'] => (float) $count['counted']])
            ->all();
    }

    /**
     * Why an item counted higher than expected, as item id => "restock" | "recipe".
     *
     * @return array<int, string>
     */
    public function surplusReasons(): array
    {
        return collect($this->validated('counts'))
            ->filter(fn (array $count) => ! empty($count['surplus']))
            ->mapWithKeys(fn (array $count) => [(int) $count['item_id'] => $count['surplus']])
            ->all();
    }
}
