<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'test_id', 'candidate_id', 'status', 'started_at', 'deadline_at',
        'submitted_at', 'last_saved_at', 'system_check_passed', 'violations',
        'question_order', 'auto_score', 'manual_score', 'total_score',
        'max_score', 'passed', 'resume_count', 'terminated',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'system_check_passed' => 'boolean',
            'terminated' => 'boolean',
            'question_order' => 'array',
            'auto_score' => 'float',
            'manual_score' => 'float',
            'total_score' => 'float',
            'max_score' => 'float',
            'passed' => 'boolean',
        ];
    }

    public function test(): BelongsTo      { return $this->belongsTo(Test::class); }
    public function candidate(): BelongsTo { return $this->belongsTo(User::class, 'candidate_id'); }
    public function answers(): HasMany     { return $this->hasMany(AttemptAnswer::class); }
}
