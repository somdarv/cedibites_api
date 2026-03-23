<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItemOptionBranchPrice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'menu_item_option_id',
        'branch_id',
        'price',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function menuItemOption(): BelongsTo
    {
        return $this->belongsTo(MenuItemOption::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
