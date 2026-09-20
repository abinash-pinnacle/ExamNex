<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\User;
use App\Services\QuestionBank;
use App\Support\Normalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Staff ----
        $admin = User::firstOrCreate(
            ['email' => 'admin@examnex.test'],
            ['name' => 'Admin', 'role' => 'ADMIN', 'password' => 'password', 'is_active' => true]
        );
        User::firstOrCreate(
            ['email' => 'creator@examnex.test'],
            ['name' => 'Test Creator', 'role' => 'TEST_CREATOR', 'password' => 'password', 'is_active' => true]
        );

        // ---- Candidates ----
        $candidates = [];
        foreach ([['Asha Rao', 'STU001'], ['Bilal Khan', 'STU002'], ['Chitra Nair', 'STU003']] as [$name, $sid]) {
            $candidates[] = User::firstOrCreate(
                ['student_id' => $sid],
                ['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)) . '@student.test',
                 'role' => 'CANDIDATE', 'password' => 'password', 'batch' => 'CSE-A', 'is_active' => true]
            );
        }

        // ---- Question bank ----
        $topicId = QuestionBank::ensureTopicPath('General Knowledge', 'Science', 'Physics', $admin->id);

        $samples = [
            ['type' => 'MCQ_SINGLE', 'text' => 'What is the SI unit of force?', 'marks' => 1,
             'options' => [['Newton', true], ['Joule', false], ['Watt', false], ['Pascal', false]]],
            ['type' => 'MCQ_MULTI', 'text' => 'Which of the following are vector quantities?', 'marks' => 2,
             'options' => [['Velocity', true], ['Speed', false], ['Acceleration', true], ['Mass', false]]],
            ['type' => 'TRUE_FALSE', 'text' => 'Light travels faster than sound.', 'marks' => 1, 'bool_answer' => true],
            ['type' => 'FILL_BLANK', 'text' => 'The acceleration due to gravity on Earth is about ___ m/s^2.', 'marks' => 1, 'correct_text' => '9.8|9.81|9.8 m/s2'],
            ['type' => 'NUMERIC', 'text' => 'How many metres are there in 2 kilometres?', 'marks' => 1, 'numeric_answer' => 2000, 'numeric_tolerance' => 0],
            ['type' => 'DESCRIPTIVE', 'text' => 'State Newton\'s first law of motion in your own words.', 'marks' => 5, 'model_answer' => 'An object stays at rest or in uniform motion unless acted on by a net external force.'],
        ];

        $path = QuestionBank::getTopicPath($topicId);
        $questionIds = [];
        foreach ($samples as $s) {
            $q = Question::firstOrCreate(
                ['normalized_text' => Normalizer::questionText($s['text'])],
                [
                    'type' => $s['type'], 'text' => $s['text'], 'topic_id' => $topicId,
                    'category' => $path['folder'], 'subject' => $path['subject'], 'topic' => $path['topic'],
                    'difficulty' => 'EASY', 'status' => 'ACTIVE', 'marks' => $s['marks'],
                    'correct_text' => $s['correct_text'] ?? null,
                    'numeric_answer' => $s['numeric_answer'] ?? null, 'numeric_tolerance' => $s['numeric_tolerance'] ?? 0,
                    'bool_answer' => $s['bool_answer'] ?? null, 'model_answer' => $s['model_answer'] ?? null,
                    'created_by' => $admin->id,
                ]
            );
            if (isset($s['options']) && $q->options()->count() === 0) {
                foreach ($s['options'] as $i => [$text, $correct]) {
                    QuestionOption::create(['question_id' => $q->id, 'text' => $text, 'is_correct' => $correct, 'order' => $i]);
                }
            }
            $questionIds[] = $q->id;
        }

        // ---- Sample test (published, assigned + public link) ----
        $test = Test::firstOrCreate(
            ['title' => 'Physics Basics — Demo Test'],
            [
                'description' => 'A short demo covering all question types.',
                'subject' => 'Science', 'category' => 'General Knowledge',
                'status' => 'PUBLISHED', 'duration_minutes' => 30, 'passing_marks' => 5,
                'max_attempts' => 2, 'result_visibility' => 'IMMEDIATE',
                'public_access' => true, 'access_code' => 'DEMO123456', 'max_candidates' => 200,
                'issue_certificate' => true, 'created_by' => $admin->id,
            ]
        );

        $order = 0;
        foreach ($questionIds as $qid) {
            TestQuestion::firstOrCreate(['test_id' => $test->id, 'question_id' => $qid], ['order' => $order++]);
        }
        $total = TestQuestion::with('question:id,marks')->where('test_id', $test->id)->get()
            ->sum(fn ($tq) => $tq->marks_override ?? $tq->question->marks);
        $test->update(['total_marks' => $total]);

        foreach ($candidates as $c) {
            $test->assignments()->firstOrCreate(['user_id' => $c->id], ['assigned_at' => now()]);
        }

        $this->command->info('Seeded. Login: admin@examnex.test / password  (creator@examnex.test, candidates *@student.test — all "password")');
        $this->command->info('Public demo link: /test/DEMO123456');
    }
}
