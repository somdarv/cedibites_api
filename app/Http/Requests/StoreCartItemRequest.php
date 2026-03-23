<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
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
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'menu_item_option_id' => ['nullable', 'exists:menu_item_options,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'special_instructions' => ['nullable', 'string'],
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
            'menu_item_id.required' => 'Menu item is required',
            'menu_item_id.exists' => 'Selected menu item does not exist',
            'menu_item_option_id.exists' => 'Selected menu item option does not exist',
            'quantity.required' => 'Quantity is required',
            'quantity.integer' => 'Quantity must be a number',
            'quantity.min' => 'Quantity must be at least 1',
            'unit_price.required' => 'Unit price is required',
            'unit_price.numeric' => 'Unit price must be a number',
            'unit_price.min' => 'Unit price cannot be negative',
        ];
    }
}
