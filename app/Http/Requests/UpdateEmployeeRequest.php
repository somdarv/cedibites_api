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
            'branch_id' => ['sometimes', 'exists:branches,id'],
            'role' => ['sometimes', Rule::enum(Role::class)],
            'status' => ['sometimes', Rule::enum(EmployeeStatus::class)],
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
            'branch_id.exists' => 'Selected branch does not exist.',
        ];
    }
}
