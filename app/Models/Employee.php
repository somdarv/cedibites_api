<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_no',
        'branch_id',
        'status',
        'hire_date',
        'performance_rating',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'hire_date' => 'date',
            'performance_rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_employee_id');
    }
}
