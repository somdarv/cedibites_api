<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateHubtelPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        // If authenticated, verify order ownership
        if ($this->user()) {
            if ($order->customer_id !== $this->user()->id) {
                return false;
            }
        }

        // Verify order is payable (not already completed)
        $completedPayment = $order->payments()
            ->where('payment_status', 'completed')
            ->exists();

        if ($completedPayment) {
            return false;
        }

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
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'regex:/^233[0-9]{9}$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
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
            'customer_name.string' => 'Customer name must be a valid string',
            'customer_name.max' => 'Customer name cannot exceed 255 characters',
            'customer_phone.string' => 'Customer phone must be a valid string',
            'customer_phone.regex' => 'Customer phone format is invalid. Use format: 233XXXXXXXXX',
            'customer_email.email' => 'Customer email must be a valid email address',
            'customer_email.max' => 'Customer email cannot exceed 255 characters',
            'description.required' => 'Payment description is required',
            'description.string' => 'Payment description must be a valid string',
            'description.max' => 'Payment description cannot exceed 500 characters',
        ];
    }
}
