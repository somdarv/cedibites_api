<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendOTPRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^\+233[0-9]{9}$/'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(
                    fn ($query) => $query->where('phone', '!=', $this->input('phone'))
                ),
            ],
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
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email is already registered',
        ];
    }
}
