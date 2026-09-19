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
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.item_id' => ['required', 'integer', 'distinct'],
            'counts.*.counted' => ['required', 'numeric', 'min:0', 'max:999999'],
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
}
