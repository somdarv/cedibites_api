<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSmartCategorySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'item_limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'visible_hour_start' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:23'],
            'visible_hour_end' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:23'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_limit.min' => 'Item limit must be at least 1.',
            'item_limit.max' => 'Item limit cannot exceed 50.',
            'visible_hour_start.min' => 'Hour must be between 0 and 23.',
            'visible_hour_end.max' => 'Hour must be between 0 and 23.',
        ];
    }
}
