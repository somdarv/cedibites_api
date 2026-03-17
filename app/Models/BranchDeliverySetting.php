<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchDeliverySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'base_delivery_fee',
        'per_km_fee',
        'delivery_radius_km',
        'min_order_value',
        'estimated_delivery_time',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_delivery_fee' => 'decimal:2',
            'per_km_fee' => 'decimal:2',
            'delivery_radius_km' => 'decimal:2',
            'min_order_value' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
