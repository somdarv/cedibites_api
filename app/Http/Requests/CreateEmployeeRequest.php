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
            'password' => ['required', 'string', 'min:8'],
            'branch_id' => ['required', 'exists:branches,id'],
            'role' => ['required', Rule::enum(Role::class)],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
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
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'branch_id.required' => 'Branch is required.',
            'branch_id.exists' => 'Selected branch does not exist.',
            'role.required' => 'Role is required.',
        ];
    }
}
