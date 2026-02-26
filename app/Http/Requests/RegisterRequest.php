<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+233[0-9]{9}$/', 'unique:users,phone'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required',
            'phone.regex' => 'Phone number must be a valid Ghana phone number (+233XXXXXXXXX)',
            'phone.unique' => 'This phone number is already registered',
            'name.required' => 'Name is required',
            'name.max' => 'Name must not exceed 255 characters',
        ];
    }
}
