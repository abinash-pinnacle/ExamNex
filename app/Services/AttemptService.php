<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * The assessment engine. Ported from actions/attempts.ts.
 * Invariants preserved:
 *  - Clock is server-authoritative (attempts.deadline_at).
 *  - Question/option order is frozen on start (question_order) for consistent resume.
 *  - One attempt metered on start; resume never re-meters and is never blocked.
 *  - Objective auto-graded on submit; descriptive queued; final score when all graded.
 *
 * Section-wise tests (Test::usesSections()) delegate paper building, timers and
 * scoring to SectionService; tests without sections keep the original behaviour.
 */
class AttemptService
{
    private static function shuffle(array $arr): array
    {
        shuffle($arr);
        return $arr;
    }

    /**
     * Start a new attempt or resume an in-progress one.
     * @return array{ok: bool, attemptId?: int, resumed?: bool, error?: string}
     */
    public static function start(Test $test, User $candidate, bool $systemCheckPassed): array
    {
        // Resume takes priority and is never blocked.
        $existing = Attempt::where('test_id', $test->id)
            ->where('candidate_id', $candidate->id)
            ->where('status', 'IN_PROGRESS')
            ->orderByDesc('started_at')
            ->first();

        if ($existing) {
            if ($existing->deadline_at->getTimestamp() * 1000 <= now()->getTimestamp() * 1000) {
                self::finalize($existing->id, $candidate->id, 'AUTO_SUBMITTED');
                return ['ok' => false, 'error' => 'Your time expired; the attempt was submitted.', 'attemptId' => $existing->id];
            }
            $existing->increment('resume_count');
            Audit::log('attempt.resume', ['entity' => 'Attempt', 'entity_id' => $existing->id]);
            return ['ok' => true, 'attemptId' => $existing->id, 'resumed' => true];
        }

        if ($test->status !== 'PUBLISHED') {
            return ['ok' => false, 'error' => 'Test is not available.'];
        }
        $now = now();
        if ($test->starts_at && $now->lt($test->starts_at)) {
            return ['ok' => false, 'error' => 'Test has not started yet.'];
        }
        if ($test->ends_at && $now->gt($test->ends_at)) {
            return ['ok' => false, 'error' => 'Test window has closed.'];
        }

        $assigned = $test->assignments()->where('user_id', $candidate->id)->exists();
        if (! $assigned) {
            return ['ok' => false, 'error' => 'You are not assigned to this test.'];
        }

        $prior = Attempt::where('test_id', $test->id)->where('candidate_id', $candidate->id)->count();
        if ($prior >= $test->max_attempts) {
            return ['ok' => false, 'error' => 'You have used all your attempts.'];
        }

        // ---- Section-wise paper: pick question_count per section, randomised within it ----
        if ($test->usesSections()) {
            $paper = SectionService::buildPaper($test);
            if (empty($paper['questions'])) {
                return ['ok' => false, 'error' => 'This test has no questions.'];
            }
            $deadline = $now->copy()->addMinutes(SectionService::durationMinutes($test));
            $attempt = Attempt::create([
                'test_id' => $test->id,
                'candidate_id' => $candidate->id,
                'status' => 'IN_PROGRESS',
                'started_at' => $now,
                'deadline_at' => $deadline,
                'system_check_passed' => $systemCheckPassed,
                'max_score' => SectionService::totalMarks($test),
                'question_order' => $paper,
                'section_state' => SectionService::initialState($paper, $now),
            ]);
            Audit::log('attempt.start', ['entity' => 'Attempt', 'entity_id' => $attempt->id]);
            return ['ok' => true, 'attemptId' => $attempt->id];
        }

        $tqs = TestQuestion::with('question.options')
            ->where('test_id', $test->id)
            ->orderBy('order')
            ->get();
        if ($tqs->isEmpty()) {
            return ['ok' => false, 'error' => 'This test has no questions.'];
        }

        $ordered = $tqs->pluck('question_id')->map('intval')->all();
        if ($test->shuffle_questions) {
            $ordered = self::shuffle($ordered);
        }
        $optionOrder = [];
        if ($test->shuffle_options) {
            foreach ($tqs as $tq) {
                $opts = $tq->question->options;
                if ($opts->count()) {
                    $optionOrder[(string) $tq->question_id] = self::shuffle($opts->pluck('id')->map('intval')->all());
                }
            }
        }

        $deadline = $now->copy()->addMinutes($test->duration_minutes);
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'candidate_id' => $candidate->id,
            'status' => 'IN_PROGRESS',
            'started_at' => $now,
            'deadline_at' => $deadline,
            'system_check_passed' => $systemCheckPassed,
            'max_score' => $test->total_marks,
            'question_order' => ['questions' => $ordered, 'options' => (object) $optionOrder],
        ]);

        Audit::log('attempt.start', ['entity' => 'Attempt', 'entity_id' => $attempt->id]);
        return ['ok' => true, 'attemptId' => $attempt->id];
    }

    /** Background auto-save; upserts one answer. */
    public static function saveAnswer(int $attemptId, int $candidateId, array $a): array
    {
        $attempt = Attempt::where('id', $attemptId)->where('candidate_id', $candidateId)->first();
        if (! $attempt) {
            return ['ok' => false, 'error' => 'Attempt not found'];
        }
        if ($attempt->status !== 'IN_PROGRESS') {
            return ['ok' => false, 'error' => 'Attempt already submitted'];
        }
        // Small grace so a save in flight at the buzzer still lands.
        if ($attempt->deadline_at->getTimestamp() * 1000 + 5000 < now()->getTimestamp() * 1000) {
            return ['ok' => false, 'error' => 'Time expired'];
        }
        // Section tests: a locked/expired section can no longer be written to.
        if (! empty($attempt->question_order['sections'])) {
            $test = $attempt->test;
            if ($test && $test->timer_mode === 'SECTION') {
                SectionService::syncTimers($attempt, $test);
                $attempt->refresh();
            }
            if ($test && ! SectionService::questionWritable($attempt, $test, (int) $a['question_id'])) {
                return ['ok' => false, 'error' => 'This section is locked'];
            }
        }

        AttemptAnswer::updateOrCreate(
            ['attempt_id' => $attemptId, 'question_id' => $a['question_id']],
            [
                'selected_option_ids' => $a['selected_option_ids'] ?? null,
                'text_answer' => $a['text_answer'] ?? null,
                'numeric_answer' => $a['numeric_answer'] ?? null,
                'bool_answer' => $a['bool_answer'] ?? null,
                'marked_for_review' => $a['marked_for_review'] ?? false,
            ]
        );
        $savedAt = now();
        $attempt->update(['last_saved_at' => $savedAt]);
        return ['ok' => true, 'savedAt' => $savedAt->toIso8601String()];
    }

    /**
     * Candidate moves to the next section (section tests only).
     * @return array{ok: bool, ended: bool}  ended = no more sections, attempt was submitted
     */
    public static function advanceSection(int $attemptId, int $candidateId): array
    {
        $attempt = Attempt::where('id', $attemptId)->where('candidate_id', $candidateId)->where('status', 'IN_PROGRESS')->first();
        if (! $attempt || empty($attempt->question_order['sections'])) {
            return ['ok' => false, 'ended' => false];
        }
        $test = $attempt->test;
        if ($test->timer_mode === 'SECTION' && SectionService::syncTimers($attempt, $test) === null) {
            self::submit($attemptId, $candidateId, true);
            return ['ok' => true, 'ended' => true];
        }
        $attempt->refresh();
        $more = SectionService::advance($attempt, $test);
        Audit::log('attempt.sectionNext', ['entity' => 'Attempt', 'entity_id' => $attemptId]);
        if (! $more) {
            self::submit($attemptId, $candidateId, false);
            return ['ok' => true, 'ended' => true];
        }
        return ['ok' => true, 'ended' => false];
    }

    /**
     * Record an integrity violation (e.g. left the exam window) and enforce the
     * warning limit ON THE SERVER. The client's terminate logic is only UX; the
     * server is authoritative, so a tampered/JS-disabled client cannot dodge FAIL.
     * @return array{ok: bool, terminated: bool}
     */
    public static function logExamEvent(int $attemptId, int $candidateId): array
    {
        $attempt = Attempt::where('id', $attemptId)
            ->where('candidate_id', $candidateId)
            ->where('status', 'IN_PROGRESS')
            ->first();
        if (! $attempt) {
            return ['ok' => true, 'terminated' => false];
        }
        $attempt->increment('violations');
        $attempt->refresh();

        $test = $attempt->test;
        // Mirror the client rule: with warnings, 1 warning is allowed then terminate;
        // without warnings, terminate on the first violation. Only tests that opt
        // into tab-switch prevention terminate.
        if ($test && $test->prevent_tab_switch) {
            $limit = $test->show_warning ? 1 : 0;
            if ($attempt->violations > $limit) {
                self::finalize($attemptId, $candidateId, 'AUTO_SUBMITTED');
                Attempt::where('id', $attemptId)->update([
                    'terminated' => true,
                    'passed' => false,
                    'status' => 'EVALUATED',
                    'result_reason' => 'Terminated for malpractice (left the exam window).',
                ]);
                Audit::log('attempt.terminated', ['entity' => 'Attempt', 'entity_id' => $attemptId, 'detail' => ['server' => true, 'violations' => $attempt->violations]]);
                return ['ok' => true, 'terminated' => true];
            }
        }
        return ['ok' => true, 'terminated' => false];
    }

    public static function submit(int $attemptId, int $candidateId, bool $auto = false, bool $terminated = false): array
    {
        $res = self::finalize($attemptId, $candidateId, $auto ? 'AUTO_SUBMITTED' : 'SUBMITTED');
        if ($res['ok'] && $terminated) {
            // Malpractice (left the exam window): force-fail and mark terminated.
            Attempt::where('id', $attemptId)->update([
                'terminated' => true,
                'passed' => false,
                'status' => 'EVALUATED',
                'result_reason' => 'Terminated for malpractice (left the exam window).',
            ]);
        }
        if ($res['ok']) {
            Audit::log($terminated ? 'attempt.terminated' : ($auto ? 'attempt.autoSubmit' : 'attempt.submit'),
                ['entity' => 'Attempt', 'entity_id' => $attemptId]);
        }
        return $res;
    }

    /**
     * Grade all objective answers and finalise. Descriptive answers left for
     * manual grading; the attempt stays SUBMITTED until they're done.
     */
    public static function finalize(int $attemptId, int $candidateId, string $status): array
    {
        return DB::transaction(function () use ($attemptId, $candidateId, $status) {
            $attempt = Attempt::where('id', $attemptId)->where('candidate_id', $candidateId)->lockForUpdate()->first();
            if (! $attempt) {
                return ['ok' => false, 'error' => 'Attempt not found'];
            }
            if ($attempt->status !== 'IN_PROGRESS') {
                return ['ok' => true, 'attemptId' => $attemptId]; // idempotent
            }

            $test = Test::findOrFail($attempt->test_id);
            $sections = $attempt->question_order['sections'] ?? [];

            if ($sections) {
                return self::finalizeSectioned($attempt, $test, $sections, $status);
            }

            $tqs = TestQuestion::with('question.options')->where('test_id', $attempt->test_id)->get();
            $answers = AttemptAnswer::where('attempt_id', $attemptId)->get()->keyBy('question_id');

            $autoScore = 0.0;
            $pendingDescriptive = false;

            foreach ($tqs as $tq) {
                $q = $tq->question;
                $ans = $answers->get($q->id);

                if ($q->type === 'DESCRIPTIVE') {
                    $answered = $ans && $ans->text_answer !== null && trim($ans->text_answer) !== '';
                    if ($answered) {
                        $pendingDescriptive = true;
                    } else {
                        // Unanswered long answer -> auto-award 0 so the attempt never deadlocks.
                        if ($ans) {
                            $ans->update(['awarded_marks' => 0, 'is_correct' => false, 'graded' => true]);
                        } else {
                            AttemptAnswer::create([
                                'attempt_id' => $attemptId, 'question_id' => $q->id,
                                'awarded_marks' => 0, 'graded' => true,
                            ]);
                        }
                    }
                    continue;
                }

                $g = Evaluation::grade($q, $ans, $test->negative_marking_on, $tq->marks_override ?? $q->marks, (float) ($test->negative_marks ?? 0));
                $autoScore += $g['awardedMarks'] ?? 0;
                if ($ans) {
                    $ans->update([
                        'is_correct' => $g['isCorrect'],
                        'awarded_marks' => $g['awardedMarks'],
                        'graded' => true,
                    ]);
                }
            }

            $fullyGraded = ! $pendingDescriptive;
            $attempt->update([
                'status' => $fullyGraded ? 'EVALUATED' : $status,
                'submitted_at' => now(),
                'auto_score' => $autoScore,
                'manual_score' => $fullyGraded ? 0 : null,
                'total_score' => $fullyGraded ? $autoScore : null,
                'passed' => $fullyGraded ? ($autoScore >= $test->passing_marks) : null,
                'result_reason' => $fullyGraded && $autoScore < $test->passing_marks ? 'Qualifying marks not achieved.' : null,
            ]);

            return ['ok' => true, 'attemptId' => $attemptId];
        });
    }

    /**
     * Section-wise finalisation: every question is graded with ITS SECTION's
     * marks-per-question and negative marking (frozen on the attempt), then the
     * section results and the combined PASS/FAIL are computed.
     */
    private static function finalizeSectioned(Attempt $attempt, Test $test, array $sections, string $status): array
    {
        $qids = [];
        $params = [];   // question id -> [mpq, neg]
        foreach ($sections as $sec) {
            foreach ($sec['qids'] as $qid) {
                $qids[] = (int) $qid;
                $params[(int) $qid] = [(int) $sec['mpq'], (float) $sec['neg']];
            }
        }
        $questions = \App\Models\Question::with('options')->whereIn('id', $qids)->get()->keyBy('id');
        $answers = AttemptAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id');

        $autoScore = 0.0;
        $pendingDescriptive = false;
        foreach ($qids as $qid) {
            $q = $questions->get($qid);
            if (! $q) {
                continue;
            }
            $ans = $answers->get($qid);
            [$mpq, $neg] = $params[$qid];

            if ($q->type === 'DESCRIPTIVE') {
                $answered = $ans && $ans->text_answer !== null && trim($ans->text_answer) !== '';
                if ($answered) {
                    $pendingDescriptive = true;
                } elseif ($ans) {
                    $ans->update(['awarded_marks' => 0, 'is_correct' => false, 'graded' => true]);
                } else {
                    AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $qid, 'awarded_marks' => 0, 'graded' => true]);
                }
                continue;
            }

            $g = Evaluation::grade($q, $ans, $neg > 0, $mpq, $neg);
            $autoScore += $g['awardedMarks'] ?? 0;
            if ($ans) {
                $ans->update(['is_correct' => $g['isCorrect'], 'awarded_marks' => $g['awardedMarks'], 'graded' => true]);
            }
        }

        $res = SectionService::score($attempt, $test);
        $fullyGraded = ! $res['pending'];
        $attempt->update([
            'status' => $fullyGraded ? 'EVALUATED' : $status,
            'submitted_at' => now(),
            'auto_score' => $autoScore,
            'manual_score' => $fullyGraded ? 0 : null,
            'total_score' => $fullyGraded ? $res['total'] : null,
            'max_score' => $res['max'],
            'passed' => $res['passed'],
            'result_reason' => $res['reason'],
        ]);

        return ['ok' => true, 'attemptId' => $attempt->id];
    }

    /** Faculty/admin manual grade for a descriptive answer. */
    public static function gradeDescriptive(int $answerId, float $awardedMarks, ?string $feedback, int $graderId): void
    {
        DB::transaction(function () use ($answerId, $awardedMarks, $feedback, $graderId) {
            $ans = AttemptAnswer::findOrFail($answerId);
            $ans->update([
                'awarded_marks' => $awardedMarks,
                'feedback' => $feedback ?: null,
                'graded' => true,
                'graded_by' => $graderId,
                'graded_at' => now(),
            ]);

            $attempt = Attempt::findOrFail($ans->attempt_id);
            $test = Test::findOrFail($attempt->test_id);

            $descIds = TestQuestion::where('test_id', $attempt->test_id)
                ->whereHas('question', fn ($q) => $q->where('type', 'DESCRIPTIVE'))
                ->pluck('question_id')->all();
            // Section tests: only the questions actually on this candidate's paper count.
            if (! empty($attempt->question_order['sections'])) {
                $onPaper = array_map('intval', $attempt->question_order['questions'] ?? []);
                $descIds = array_values(array_intersect(array_map('intval', $descIds), $onPaper));
            }
            $descAnswers = AttemptAnswer::where('attempt_id', $attempt->id)
                ->whereIn('question_id', $descIds)->get()->keyBy('question_id');

            $allGraded = collect($descIds)->every(fn ($qid) => (bool) ($descAnswers->get($qid)?->graded));

            if ($allGraded) {
                $manual = $descAnswers->sum(fn ($a) => $a->awarded_marks ?? 0);
                if (! empty($attempt->question_order['sections'])) {
                    $res = SectionService::score($attempt, $test);
                    $attempt->update([
                        'manual_score' => $manual,
                        'total_score' => $res['total'],
                        'max_score' => $res['max'],
                        'passed' => $res['passed'],
                        'result_reason' => $res['reason'],
                        'status' => 'EVALUATED',
                    ]);
                } else {
                    $total = ($attempt->auto_score ?? 0) + $manual;
                    $attempt->update([
                        'manual_score' => $manual,
                        'total_score' => $total,
                        'passed' => $total >= $test->passing_marks,
                        'result_reason' => $total >= $test->passing_marks ? null : 'Qualifying marks not achieved.',
                        'status' => 'EVALUATED',
                    ]);
                }
            }
        });
        Audit::log('answer.grade', ['entity' => 'AttemptAnswer', 'entity_id' => $answerId]);
    }
}
