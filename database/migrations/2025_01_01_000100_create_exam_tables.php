<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-tenant exam platform schema (ported from the Prisma multi-tenant model).
 * All tenantId columns and Row-Level Security are removed — one organisation only.
 * Question bank hierarchy: Folder -> Subject -> Topic -> Question.
 * Global duplicate prevention: questions.normalized_text UNIQUE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Question bank hierarchy ----
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['folder_id', 'name']);
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['subject_id', 'name']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            // MCQ_SINGLE | MCQ_MULTI | TRUE_FALSE | FILL_BLANK | NUMERIC | DESCRIPTIVE
            $table->string('type');
            $table->text('text');
            $table->string('normalized_text', 500)->default('');
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            // Denormalised strings kept in sync for reports / back-compat.
            $table->string('category')->nullable(); // folder name
            $table->string('subject')->nullable();
            $table->string('topic')->nullable();
            $table->string('difficulty')->nullable(); // EASY | MEDIUM | HARD
            $table->string('status')->default('ACTIVE'); // ACTIVE | DRAFT | ARCHIVED
            $table->integer('marks')->default(1);
            $table->float('negative_marks')->default(0);
            $table->string('correct_text')->nullable();   // fill-blank accepted answers, '|' separated
            $table->float('numeric_answer')->nullable();
            $table->float('numeric_tolerance')->default(0);
            $table->boolean('bool_answer')->nullable();
            $table->text('model_answer')->nullable();      // reference answer for descriptive
            $table->text('explanation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('normalized_text'); // global duplicate prevention
            $table->index('topic_id');
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->integer('order')->default(0);
            $table->index('question_id');
        });

        // ---- Tests ----
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('subject')->nullable();
            $table->string('category')->nullable();
            $table->string('audience')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT | PUBLISHED | ARCHIVED
            $table->integer('duration_minutes')->default(60);
            $table->integer('total_marks')->default(0);
            $table->integer('passing_marks')->default(0);
            $table->boolean('negative_marking_on')->default(false);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->integer('max_attempts')->default(1);
            // Shared public link (self-registration).
            $table->boolean('public_access')->default(false);
            $table->string('access_code')->nullable()->unique();
            $table->integer('max_candidates')->default(200);
            $table->string('result_visibility')->default('AFTER_REVIEW'); // IMMEDIATE | AFTER_REVIEW | HIDDEN
            $table->boolean('issue_certificate')->default(false);
            $table->text('instructions')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('test_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->string('title');
            $table->integer('order')->default(0);
            $table->integer('marks')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('test_sections')->nullOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->integer('marks_override')->nullable();
            $table->unique(['test_id', 'question_id']);
        });

        Schema::create('test_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->unique(['test_id', 'user_id']);
        });

        // ---- Attempts (the assessment engine) ----
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('IN_PROGRESS'); // IN_PROGRESS | SUBMITTED | AUTO_SUBMITTED | EVALUATED
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('deadline_at');       // server-authoritative clock
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->boolean('system_check_passed')->default(false);
            $table->integer('violations')->default(0);
            $table->json('question_order')->nullable(); // frozen paper for consistent resume
            $table->float('auto_score')->nullable();
            $table->float('manual_score')->nullable();
            $table->float('total_score')->nullable();
            $table->float('max_score')->nullable();
            $table->boolean('passed')->nullable();
            $table->integer('resume_count')->default(0);
            $table->index(['test_id', 'candidate_id']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->json('selected_option_ids')->nullable();
            $table->text('text_answer')->nullable();
            $table->float('numeric_answer')->nullable();
            $table->boolean('bool_answer')->nullable();
            $table->boolean('marked_for_review')->default(false);
            $table->boolean('is_correct')->nullable();
            $table->float('awarded_marks')->nullable();
            $table->boolean('graded')->default(false);
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('action');
            $table->string('entity')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('attempts');
        Schema::dropIfExists('test_assignments');
        Schema::dropIfExists('test_questions');
        Schema::dropIfExists('test_sections');
        Schema::dropIfExists('tests');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('folders');
    }
};
