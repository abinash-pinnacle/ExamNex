<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = ['test_id', 'user_id', 'assigned_at'];

    protected function casts(): array { return ['assigned_at' => 'datetime']; }

    public function test(): BelongsTo { return $this->belongsTo(Test::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
