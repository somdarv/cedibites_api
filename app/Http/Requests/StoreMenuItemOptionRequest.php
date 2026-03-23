<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $menuItem = $this->route('menuItem');

        return [
            'option_key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('menu_item_options', 'option_key')->where('menu_item_id', $menuItem->id),
            ],
            'option_label' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_available' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'option_key.required' => 'Option key is required',
            'option_key.regex' => 'Option key may only contain lowercase letters, numbers, hyphens, and underscores',
            'option_label.required' => 'Option label is required',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price cannot be negative',
        ];
    }
}
