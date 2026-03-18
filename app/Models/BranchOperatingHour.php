<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOperatingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
        'manual_override_open',
        'manual_override_at',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'manual_override_open' => 'boolean',
            'manual_override_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Determine if this day is currently open based on schedule and manual overrides.
     */
    public function isCurrentlyOpen(): bool
    {
        // If there's a manual override, use that
        if ($this->manual_override_open !== null) {
            return $this->manual_override_open;
        }

        // If not scheduled to be open today, return false
        if (! $this->is_open) {
            return false;
        }

        // Check if current time is within operating hours
        $currentTime = now()->format('H:i:s');

        return $currentTime >= $this->open_time && $currentTime <= $this->close_time;
    }
}
