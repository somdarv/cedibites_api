<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_size_id',
        'menu_item_snapshot',
        'menu_item_size_snapshot',
        'quantity',
        'unit_price',
        'subtotal',
        'special_instructions',
    ];

    protected function casts(): array
    {
        return [
            'menu_item_snapshot' => 'array',
            'menu_item_size_snapshot' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function menuItemSize(): BelongsTo
    {
        return $this->belongsTo(MenuItemSize::class);
    }
}
