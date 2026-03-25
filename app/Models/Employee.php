<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logOnly(['user_id', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'user_id',
        'employee_no',
        'status',
        'hire_date',
        'performance_rating',
        // HR Information
        'ssnit_number',
        'ghana_card_id',
        'tin_number',
        'date_of_birth',
        'nationality',
        // Emergency Contact
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'hire_date' => 'date',
            'date_of_birth' => 'date',
            'performance_rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'employee_branch')->withTimestamps();
    }

    /**
     * Get branches that this employee manages.
     * Returns branches where this employee has the 'manager' role.
     */
    public function managedBranches(): BelongsToMany
    {
        return $this->branches()
            ->whereHas('employees', function ($query) {
                $query->where('employees.id', $this->id)
                    ->whereHas('user.roles', function ($roleQuery) {
                        $roleQuery->where('name', 'manager');
                    });
            });
    }

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_employee_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
