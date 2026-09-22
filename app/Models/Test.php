<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Test extends Model
{
    protected $fillable = [
        'title', 'description', 'subject', 'category', 'audience', 'status',
        'duration_minutes', 'total_marks', 'passing_marks', 'passing_percent',
        'negative_marking_on', 'shuffle_questions', 'shuffle_options',
        'max_attempts', 'public_access', 'access_code', 'max_candidates',
        'result_visibility', 'issue_certificate', 'instructions',
        'starts_at', 'ends_at', 'created_by',
        // options
        'one_per_page', 'allow_review', 'autosave', 'full_screen', 'prevent_tab_switch',
        'detect_copy', 'show_warning', 'restrict_right_click', 'webcam_proctoring',
        'browser_lockdown', 'ai_cheating_detection', 'show_solution', 'allow_download_result',
        'email_notification', 'feedback_form', 'completion_message', 'redirect_url',
        'grace_minutes', 'require_registration', 'access_password', 'is_template', 'question_order',
        'negative_marks',
        'use_sections', 'section_navigation', 'timer_mode', 'allow_section_return',
    ];

    protected function casts(): array
    {
        return [
            'negative_marking_on' => 'boolean',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'public_access' => 'boolean',
            'issue_certificate' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'one_per_page' => 'boolean',
            'allow_review' => 'boolean',
            'autosave' => 'boolean',
            'full_screen' => 'boolean',
            'prevent_tab_switch' => 'boolean',
            'detect_copy' => 'boolean',
            'show_warning' => 'boolean',
            'restrict_right_click' => 'boolean',
            'webcam_proctoring' => 'boolean',
            'browser_lockdown' => 'boolean',
            'ai_cheating_detection' => 'boolean',
            'show_solution' => 'boolean',
            'allow_download_result' => 'boolean',
            'email_notification' => 'boolean',
            'feedback_form' => 'boolean',
            'require_registration' => 'boolean',
            'is_template' => 'boolean',
            'passing_percent' => 'integer',
            'use_sections' => 'boolean',
            'allow_section_return' => 'boolean',
        ];
    }

    public function sections(): HasMany     { return $this->hasMany(TestSection::class)->orderBy('order'); }
    public function testQuestions(): HasMany { return $this->hasMany(TestQuestion::class)->orderBy('order'); }
    public function assignments(): HasMany   { return $this->hasMany(TestAssignment::class); }
    public function attempts(): HasMany      { return $this->hasMany(Attempt::class); }
    public function creator(): BelongsTo     { return $this->belongsTo(User::class, 'created_by'); }

    /** True when this test runs the section-wise engine (flag on AND at least one section). */
    public function usesSections(): bool
    {
        return (bool) $this->use_sections && $this->sections()->exists();
    }
}
