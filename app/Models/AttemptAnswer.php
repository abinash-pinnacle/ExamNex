<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id', 'question_id', 'selected_option_ids', 'text_answer',
        'numeric_answer', 'bool_answer', 'marked_for_review', 'is_correct',
        'awarded_marks', 'graded', 'graded_by', 'graded_at', 'feedback',
    ];

    protected function casts(): array
    {
        return [
            'selected_option_ids' => 'array',
            'numeric_answer' => 'float',
            'bool_answer' => 'boolean',
            'marked_for_review' => 'boolean',
            'is_correct' => 'boolean',
            'awarded_marks' => 'float',
            'graded' => 'boolean',
            'graded_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo  { return $this->belongsTo(Attempt::class); }
    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
    public function grader(): BelongsTo   { return $this->belongsTo(User::class, 'graded_by'); }
}
