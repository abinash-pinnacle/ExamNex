<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_email', 'action', 'entity', 'entity_id', 'detail', 'created_at',
    ];

    protected function casts(): array
    {
        return ['detail' => 'array', 'created_at' => 'datetime'];
    }
}
