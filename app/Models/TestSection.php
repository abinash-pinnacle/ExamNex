<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestSection extends Model
{
    protected $fillable = ['test_id', 'title', 'order', 'marks', 'duration_minutes'];

    public function test(): BelongsTo { return $this->belongsTo(Test::class); }
}
