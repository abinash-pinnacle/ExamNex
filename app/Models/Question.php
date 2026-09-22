<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = [
        'type', 'text', 'image_path', 'normalized_text', 'topic_id',
        'category', 'subject', 'topic', 'difficulty', 'status',
        'marks', 'negative_marks', 'correct_text',
        'numeric_answer', 'numeric_tolerance', 'bool_answer',
        'model_answer', 'explanation', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'marks' => 'integer',
            'negative_marks' => 'float',
            'numeric_answer' => 'float',
            'numeric_tolerance' => 'float',
            'bool_answer' => 'boolean',
        ];
    }

    public function options(): HasMany   { return $this->hasMany(QuestionOption::class)->orderBy('order'); }
    public function topicRef(): BelongsTo { return $this->belongsTo(Topic::class, 'topic_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
}
