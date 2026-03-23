<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static $recordEvents = ['updated'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('orders')
            ->logOnly(['status', 'cancelled_at', 'cancelled_reason'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'order_number',
        'customer_id',
        'branch_id',
        'assigned_employee_id',
        'order_type',
        'order_source',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'contact_name',
        'contact_phone',
        'delivery_note',
        'subtotal',
        'delivery_fee',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'status',
        'estimated_prep_time',
        'estimated_delivery_time',
        'actual_delivery_time',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'delivery_latitude' => 'decimal:8',
            'delivery_longitude' => 'decimal:8',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'estimated_prep_time' => 'integer',
            'estimated_delivery_time' => 'datetime',
            'actual_delivery_time' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
