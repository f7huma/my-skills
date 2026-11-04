<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemCheck extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'checked_at' => 'datetime'];
    }
}
