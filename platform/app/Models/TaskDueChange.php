<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskDueChange extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_due_on' => 'date', 'new_due_on' => 'date', 'created_at' => 'datetime'];
    }
}
