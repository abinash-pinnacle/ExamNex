<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestQuestion extends Model
{
    public $timestamps = false;

    protected $fillable = ['test_id', 'section_id', 'question_id', 'order', 'marks_override'];

    public function test(): BelongsTo     { return $this->belongsTo(Test::class); }
    public function question(): BelongsTo  { return $this->belongsTo(Question::class); }
    public function section(): BelongsTo   { return $this->belongsTo(TestSection::class, 'section_id'); }
}
