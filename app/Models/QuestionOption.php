<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['question_id', 'text', 'is_correct', 'order'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'order' => 'integer'];
    }

    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
}
