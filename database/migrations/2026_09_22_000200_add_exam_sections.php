<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section-wise exam system. Everything is additive and defaults to the
 * pre-existing behaviour (use_sections = false), so old tests keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            if (! Schema::hasColumn('tests', 'use_sections')) {
                $table->boolean('use_sections')->default(false)->after('question_order');
            }
            if (! Schema::hasColumn('tests', 'section_navigation')) {
                $table->string('section_navigation', 20)->default('FREE')->after('use_sections'); // FREE | SEQUENTIAL
            }
            if (! Schema::hasColumn('tests', 'timer_mode')) {
                $table->string('timer_mode', 20)->default('OVERALL')->after('section_navigation'); // OVERALL | SECTION
            }
            if (! Schema::hasColumn('tests', 'allow_section_return')) {
                $table->boolean('allow_section_return')->default(true)->after('timer_mode');
            }
        });

        Schema::table('test_sections', function (Blueprint $table) {
            if (! Schema::hasColumn('test_sections', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (! Schema::hasColumn('test_sections', 'question_count')) {
                $table->integer('question_count')->default(0)->after('order');
            }
            if (! Schema::hasColumn('test_sections', 'marks_per_question')) {
                $table->integer('marks_per_question')->default(1)->after('question_count');
            }
            if (! Schema::hasColumn('test_sections', 'qualifying_marks')) {
                $table->float('qualifying_marks')->default(0)->after('marks_per_question');
            }
            if (! Schema::hasColumn('test_sections', 'negative_marks')) {
                $table->float('negative_marks')->default(0)->after('qualifying_marks');
            }
            if (! Schema::hasColumn('test_sections', 'selection_method')) {
                $table->string('selection_method', 20)->default('RANDOM')->after('negative_marks'); // RANDOM | SEQUENTIAL
            }
            if (! Schema::hasColumn('test_sections', 'shuffle_questions')) {
                $table->boolean('shuffle_questions')->default(true)->after('selection_method');
            }
            if (! Schema::hasColumn('test_sections', 'shuffle_options')) {
                $table->boolean('shuffle_options')->default(true)->after('shuffle_questions');
            }
            if (! Schema::hasColumn('test_sections', 'is_mandatory')) {
                $table->boolean('is_mandatory')->default(true)->after('shuffle_options');
            }
        });

        Schema::table('attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('attempts', 'section_state')) {
                $table->json('section_state')->nullable()->after('question_order');
            }
            if (! Schema::hasColumn('attempts', 'result_reason')) {
                $table->string('result_reason')->nullable()->after('passed');
            }
        });

        if (! Schema::hasTable('attempt_section_results')) {
            Schema::create('attempt_section_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
                $table->foreignId('section_id')->nullable()->constrained('test_sections')->nullOnDelete();
                $table->string('section_title');      // snapshot (survives section edits)
                $table->integer('order')->default(0);
                $table->integer('correct')->default(0);
                $table->integer('wrong')->default(0);
                $table->integer('unanswered')->default(0);
                $table->float('score')->default(0);
                $table->float('max_score')->default(0);
                $table->float('qualifying_marks')->default(0);
                $table->boolean('is_mandatory')->default(true);
                $table->boolean('passed')->nullable();  // null while descriptive grading is pending
                $table->timestamps();
                $table->unique(['attempt_id', 'section_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_section_results');
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['section_state', 'result_reason']);
        });
        Schema::table('test_sections', function (Blueprint $table) {
            $table->dropColumn(['description', 'question_count', 'marks_per_question', 'qualifying_marks', 'negative_marks',
                'selection_method', 'shuffle_questions', 'shuffle_options', 'is_mandatory']);
        });
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn(['use_sections', 'section_navigation', 'timer_mode', 'allow_section_return']);
        });
    }
};
