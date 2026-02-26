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
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_active' => 'boolean',
            'operating_hours' => 'nullable|string',
            'delivery_fee' => 'required|numeric|min:0',
            'delivery_radius_km' => 'required|numeric|min:0',
            'estimated_delivery_time' => 'nullable|string',
        ];
    }
}
