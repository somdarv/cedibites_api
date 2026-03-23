<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemOptionRequest extends FormRequest
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
        $option = $this->route('option');

        return [
            'option_key' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('menu_item_options', 'option_key')
                    ->where('menu_item_id', $menuItem->id)
                    ->ignore($option->id),
            ],
            'option_label' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
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
            'option_key.regex' => 'Option key may only contain lowercase letters, numbers, hyphens, and underscores',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price cannot be negative',
        ];
    }
}
