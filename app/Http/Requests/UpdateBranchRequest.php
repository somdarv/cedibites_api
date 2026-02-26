<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'area' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string|max:20',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'is_active' => 'boolean',
            'operating_hours' => 'nullable|string',
            'delivery_fee' => 'sometimes|numeric|min:0',
            'delivery_radius_km' => 'sometimes|numeric|min:0',
            'estimated_delivery_time' => 'nullable|string',
        ];
    }
}
