<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_active' => 'boolean',
            'manager_id' => 'nullable|exists:employees,id',

            // Operating hours
            'operating_hours' => 'nullable|array',
            'operating_hours.*.is_open' => 'boolean',
            'operating_hours.*.open_time' => 'nullable|date_format:H:i',
            'operating_hours.*.close_time' => 'nullable|date_format:H:i',

            // Delivery settings
            'delivery_settings' => 'nullable|array',
            'delivery_settings.base_delivery_fee' => 'required_with:delivery_settings|numeric|min:0',
            'delivery_settings.per_km_fee' => 'nullable|numeric|min:0',
            'delivery_settings.delivery_radius_km' => 'required_with:delivery_settings|numeric|min:0',
            'delivery_settings.min_order_value' => 'nullable|numeric|min:0',
            'delivery_settings.estimated_delivery_time' => 'nullable|string',

            // Order types
            'order_types' => 'nullable|array',
            'order_types.*.is_enabled' => 'boolean',
            'order_types.*.metadata' => 'nullable|array',

            // Payment methods
            'payment_methods' => 'nullable|array',
            'payment_methods.*.is_enabled' => 'boolean',
            'payment_methods.*.metadata' => 'nullable|array',
        ];
    }
}
