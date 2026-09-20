<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\BusinessStatus;
use App\Http\Requests\Admin\UpdateBusinessProfileRequest;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The operator editing one shop: its details, its subscription, and the owner's
 * name and email. Suspension is not here — it has its own action, because it
 * needs a reason and the operator's password.
 */
class UpdateBusinessRequest extends FormRequest
{
    /**
     * Statuses the operator may set by hand.
     *
     * @var list<string>
     */
    public const EDITABLE_STATUSES = [
        BusinessStatus::Trial->value,
        BusinessStatus::Active->value,
        BusinessStatus::PastDue->value,
    ];

    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_SUPER_ADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ownerId = $this->business()?->owner?->id;

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(UpdateBusinessProfileRequest::BUSINESS_TYPES)],
            'address' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\- ]+$/'],
            'receipt_footer' => ['nullable', 'string', 'max:120'],

            'plan' => ['required', Rule::exists('plans', 'key')],
            'plan_price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'status' => ['required', Rule::in(self::EDITABLE_STATUSES)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            'owner_name' => [Rule::requiredIf($ownerId !== null), 'nullable', 'string', 'max:255'],
            'owner_email' => [
                Rule::requiredIf($ownerId !== null),
                'nullable', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($ownerId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tin.regex' => 'TIN can only have numbers and dashes.',
            'owner_email.unique' => 'Another account already uses that email.',
            'due_date.after_or_equal' => 'The due date cannot be before the start date.',
        ];
    }

    private function business(): ?Business
    {
        $business = $this->route('business');

        return $business instanceof Business ? $business : null;
    }
}
