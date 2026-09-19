<?php

namespace App\Http\Requests\Admin;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCashierPermissionsRequest extends FormRequest
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
        $rules = ['permissions' => ['required', 'array']];

        foreach (array_keys(Business::CASHIER_PERMISSIONS) as $permission) {
            $rules["permissions.{$permission}"] = ['required', 'boolean'];
        }

        return $rules;
    }
}
