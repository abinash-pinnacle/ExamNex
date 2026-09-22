<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stored section-wise outcome of one attempt (score, counts, qualified or not). */
class AttemptSectionResult extends Model
{
    protected $fillable = [
        'attempt_id', 'section_id', 'section_title', 'order', 'correct', 'wrong', 'unanswered',
        'score', 'max_score', 'qualifying_marks', 'is_mandatory', 'passed',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'correct' => 'integer',
            'wrong' => 'integer',
            'unanswered' => 'integer',
            'score' => 'float',
            'max_score' => 'float',
            'qualifying_marks' => 'float',
            'is_mandatory' => 'boolean',
            'passed' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo { return $this->belongsTo(Attempt::class); }
    public function section(): BelongsTo { return $this->belongsTo(TestSection::class, 'section_id'); }

    public function percent(): ?int
    {
        return $this->max_score > 0 ? (int) round($this->score / $this->max_score * 100) : null;
    }
}
