<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncMenuItemBranchOptionsRequest extends FormRequest
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
        return [
            'branches' => ['required', 'array'],
            'branches.*' => ['required', 'array'],
            'branches.*.options' => ['required', 'array', 'min:1'],
            'branches.*.options.*.option_key' => ['required', 'string', 'max:255'],
            'branches.*.options.*.price' => ['nullable', 'numeric', 'min:0'],
            'branches.*.options.*.is_available' => ['nullable', 'boolean'],
        ];
    }
}
