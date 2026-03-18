<?php

namespace App\Http\Requests;

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $userId = $this->route('employee')->user_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['sometimes', 'string', Rule::unique('users', 'phone')->ignore($userId)],
            'branch_ids' => ['sometimes', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer', 'exists:branches,id'],
            'role' => ['sometimes', Rule::enum(Role::class)],
            'status' => ['sometimes', Rule::enum(EmployeeStatus::class)],
            'pos_pin' => ['nullable', 'string', 'size:4', 'regex:/^\d{4}$/'],

            // HR Information
            'ssnit_number' => ['nullable', 'string', 'max:255'],
            'ghana_card_id' => ['nullable', 'string', 'max:255'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:255'],

            // Emergency Contact
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:255'],

            // Individual permissions (array of permission names)
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already registered.',
            'email.unique' => 'This email is already registered.',
            'branch_ids.*.exists' => 'Selected branch does not exist.',
            'pos_pin.size' => 'POS PIN must be exactly 4 digits.',
            'pos_pin.regex' => 'POS PIN must contain only numbers.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'permissions.*.exists' => 'Invalid permission specified.',
        ];
    }
}
