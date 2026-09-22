<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One section of a section-wise test (e.g. "General Knowledge · 10 Q · qualify 4").
 * Questions attached via test_questions.section_id form the section's POOL;
 * question_count of them are picked (randomised within the section) per attempt.
 */
class TestSection extends Model
{
    protected $fillable = [
        'test_id', 'title', 'description', 'order', 'marks', 'duration_minutes',
        'question_count', 'marks_per_question', 'qualifying_marks', 'negative_marks',
        'selection_method', 'shuffle_questions', 'shuffle_options', 'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'question_count' => 'integer',
            'marks_per_question' => 'integer',
            'qualifying_marks' => 'float',
            'negative_marks' => 'float',
            'duration_minutes' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'is_mandatory' => 'boolean',
        ];
    }

    public function test(): BelongsTo          { return $this->belongsTo(Test::class); }
    public function testQuestions(): HasMany   { return $this->hasMany(TestQuestion::class, 'section_id')->orderBy('order'); }
    public function results(): HasMany         { return $this->hasMany(AttemptSectionResult::class, 'section_id'); }

    /** Maximum marks obtainable in this section. */
    public function maxMarks(): int
    {
        return (int) $this->question_count * (int) $this->marks_per_question;
    }
}
