<?php

namespace App\Models;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'event',
        'causer_type',
        'causer_id',
        'properties',
        'batch_uuid',
        'ip_address',
    ];

    protected $casts = [
        'properties' => 'collection',
    ];

    /**
     * Boot the model and set up event listeners to capture IP addresses.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activity) {
            if (app()->runningInConsole()) {
                return;
            }

            $request = app(Request::class);
            if ($request) {
                $activity->ip_address = $request->ip();
            }
        });
    }
}
