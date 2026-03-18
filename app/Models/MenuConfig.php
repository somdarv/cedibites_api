<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuConfig extends Model
{
    protected $table = 'menu_config';

    protected $fillable = ['config'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }
}
