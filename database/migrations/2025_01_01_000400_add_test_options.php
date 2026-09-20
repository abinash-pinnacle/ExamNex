<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            // Question settings
            $table->boolean('one_per_page')->default(true);
            $table->boolean('allow_review')->default(false);
            $table->boolean('autosave')->default(true);
            // Security & proctoring
            $table->boolean('full_screen')->default(false);
            $table->boolean('prevent_tab_switch')->default(true);
            $table->boolean('detect_copy')->default(true);
            $table->boolean('show_warning')->default(true);
            $table->boolean('restrict_right_click')->default(true);
            $table->boolean('webcam_proctoring')->default(false);
            $table->boolean('browser_lockdown')->default(false);
            $table->boolean('ai_cheating_detection')->default(false);
            // Extra features
            $table->boolean('show_solution')->default(false);
            $table->boolean('allow_download_result')->default(false);
            $table->boolean('email_notification')->default(false);
            $table->boolean('feedback_form')->default(false);
            $table->text('completion_message')->nullable();
            $table->string('redirect_url')->nullable();
            // Schedule & access
            $table->integer('grace_minutes')->default(0);
            $table->boolean('require_registration')->default(true);
            $table->string('access_password')->nullable();
            // Templates
            $table->boolean('is_template')->default(false);
            $table->string('question_order')->default('SHUFFLE'); // SHUFFLE | SEQUENTIAL
            $table->float('negative_marks')->default(0); // test-level penalty per wrong answer
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn([
                'one_per_page', 'allow_review', 'autosave', 'full_screen', 'prevent_tab_switch',
                'detect_copy', 'show_warning', 'restrict_right_click', 'webcam_proctoring',
                'browser_lockdown', 'ai_cheating_detection', 'show_solution', 'allow_download_result',
                'email_notification', 'feedback_form', 'completion_message', 'redirect_url',
                'grace_minutes', 'require_registration', 'access_password', 'is_template', 'question_order',
                'negative_marks',
            ]);
        });
    }
};
