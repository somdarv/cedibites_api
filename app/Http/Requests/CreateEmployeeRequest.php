<?php

namespace App\Http\Requests;

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmployeeRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'unique:users,phone'],
            'password' => ['nullable', 'string', 'min:8'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer', 'exists:branches,id'],
            'role' => ['required', Rule::enum(Role::class)],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],

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
            'name.required' => 'Employee name is required.',
            'phone.required' => 'Phone number is required.',
            'phone.unique' => 'This phone number is already registered.',
            'email.unique' => 'This email is already registered.',
            'password.min' => 'Password must be at least 8 characters.',
            'branch_ids.required' => 'At least one branch is required.',
            'branch_ids.*.exists' => 'Selected branch does not exist.',
            'role.required' => 'Role is required.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'permissions.*.exists' => 'Invalid permission specified.',
        ];
    }
}
