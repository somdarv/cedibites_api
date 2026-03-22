<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuAddOnRequest extends FormRequest
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
        $addOn = $this->route('menu_add_on');

        return [
            'branch_id' => ['sometimes', 'exists:branches,id'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('menu_add_ons', 'slug')
                    ->where('branch_id', $this->input('branch_id', $addOn->branch_id))
                    ->ignore($addOn->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_per_piece' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
