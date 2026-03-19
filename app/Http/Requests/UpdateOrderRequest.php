<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Valid status transitions: from → allowed next statuses.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'received' => ['accepted', 'preparing', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['out_for_delivery', 'ready_for_pickup', 'completed', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'ready_for_pickup' => ['completed', 'cancelled'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

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
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'delivery_address' => ['nullable', 'string'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'contact_phone' => ['sometimes', 'string', 'max:20'],
            'delivery_note' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:received,accepted,preparing,ready,out_for_delivery,delivered,ready_for_pickup,completed,cancelled'],
            'estimated_prep_time' => ['nullable', 'integer', 'min:0'],
            'estimated_delivery_time' => ['nullable', 'date'],
            'actual_delivery_time' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            'cancelled_reason' => ['nullable', 'string'],
        ];
    }

    /**
     * Add after-validation hook to enforce valid status transitions.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $newStatus = $this->input('status');

            if (! $newStatus) {
                return;
            }

            /** @var Order $order */
            $order = $this->route('order');

            if (! $order instanceof Order) {
                return;
            }

            $currentStatus = $order->status;
            $allowed = self::TRANSITIONS[$currentStatus] ?? [];

            if (! in_array($newStatus, $allowed, true)) {
                $v->errors()->add(
                    'status',
                    "Cannot transition order from '{$currentStatus}' to '{$newStatus}'."
                );
            }
        });
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_employee_id.exists' => 'Selected employee does not exist',
            'status.in' => 'Invalid order status',
        ];
    }
}
