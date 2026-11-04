<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'interval_from_last_same_type' => 'boolean'];
    }
}
