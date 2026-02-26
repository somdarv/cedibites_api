<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'delivery_address' => ['nullable', 'string'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'contact_phone' => ['sometimes', 'string', 'max:20'],
            'delivery_note' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:received,preparing,ready,out_for_delivery,delivered,ready_for_pickup,completed,cancelled'],
            'estimated_prep_time' => ['nullable', 'integer', 'min:0'],
            'estimated_delivery_time' => ['nullable', 'date'],
            'actual_delivery_time' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            'cancelled_reason' => ['nullable', 'string'],
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
            'assigned_employee_id.exists' => 'Selected employee does not exist',
            'status.in' => 'Invalid order status',
        ];
    }
}
