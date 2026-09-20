<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_SUPER_ADMIN;
    }

    /**
     * The key is what `businesses.plan` stores, so it is only accepted on create.
     */
    protected function prepareForValidation(): void
    {
        if ($this->plan() === null) {
            $this->merge(['key' => Str::slug((string) ($this->input('key') ?: $this->input('name')))]);
        }

        $this->merge([
            'staff_limit' => $this->boolean('unlimited_staff') ? null : $this->input('staff_limit'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'pitch' => ['nullable', 'string', 'max:160'],
            'staff_limit' => ['nullable', 'integer', 'min:1', 'max:999'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'features' => ['array'],
            'features.*' => ['boolean'],
            'feature_list' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->plan() === null) {
            $rules['key'] = ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/', Rule::unique('plans', 'key')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'The plan key can only use lowercase letters, numbers and dashes.',
            'key.unique' => 'A plan with that key already exists.',
        ];
    }

    /**
     * The values to save, with the feature switches and bullet list normalised.
     *
     * @return array<string, mixed>
     */
    public function planAttributes(): array
    {
        $submitted = (array) $this->input('features', []);

        $attributes = [
            'name' => $this->validated('name'),
            'price' => $this->validated('price'),
            'pitch' => $this->validated('pitch'),
            'staff_limit' => $this->validated('staff_limit'),
            'sort' => $this->validated('sort') ?? 0,
            'features' => collect(array_keys(Plan::FEATURES))
                ->mapWithKeys(fn (string $feature): array => [$feature => (bool) ($submitted[$feature] ?? false)])
                ->all(),
            'feature_list' => collect(preg_split('/\r\n|\r|\n/', (string) $this->validated('feature_list')))
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->values()
                ->all(),
        ];

        if ($this->plan() === null) {
            $attributes['key'] = $this->validated('key');
        }

        return $attributes;
    }

    private function plan(): ?Plan
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan ? $plan : null;
    }
}
