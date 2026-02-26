<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
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
            'branch_id' => ['required', 'exists:branches,id'],
            'category_id' => ['nullable', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['boolean'],
            'is_popular' => ['boolean'],
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
            'branch_id.required' => 'Branch is required',
            'branch_id.exists' => 'Selected branch does not exist',
            'category_id.exists' => 'Selected category does not exist',
            'name.required' => 'Menu item name is required',
            'slug.required' => 'Slug is required',
            'base_price.numeric' => 'Base price must be a number',
            'base_price.min' => 'Base price cannot be negative',
        ];
    }
}
