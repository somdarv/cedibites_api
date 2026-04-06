<?php

namespace App\Models;

use App\Enums\SmartCategory;
use Illuminate\Database\Eloquent\Model;

class SmartCategorySetting extends Model
{
    protected $fillable = [
        'slug',
        'is_enabled',
        'display_order',
        'item_limit',
        'visible_hour_start',
        'visible_hour_end',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'display_order' => 'integer',
            'item_limit' => 'integer',
            'visible_hour_start' => 'integer',
            'visible_hour_end' => 'integer',
        ];
    }

    /** Resolve the SmartCategory enum from the slug. */
    public function smartCategory(): SmartCategory
    {
        return SmartCategory::from($this->slug);
    }

    /** Whether this category has a custom time window (overrides enum default). */
    public function hasCustomTimeWindow(): bool
    {
        return $this->visible_hour_start !== null && $this->visible_hour_end !== null;
    }
}
