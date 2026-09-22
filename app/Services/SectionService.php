<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptSectionResult;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\TestSection;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Section-wise exam engine (competitive-exam style).
 *
 *  - Each section owns a POOL of questions (test_questions.section_id) and a
 *    required question_count; per attempt, question_count questions are picked
 *    from the pool, randomised WITHIN the section, never mixed across sections.
 *  - The picked paper AND every section's grading parameters are frozen on the
 *    attempt (question_order.sections) so results stay consistent even if the
 *    admin edits the section later.
 *  - Scoring is section-wise: each section must reach its qualifying marks, and
 *    the overall qualifying marks (tests.passing_marks) must also be met.
 *  - Timers: OVERALL (one clock) or SECTION (per-section clock; expiry locks the
 *    section and moves on). Both are server-authoritative.
 */
class SectionService
{
    // ------------------------------------------------------------------ config

    /** Ordered sections of a test with their pool sizes attached (->pool_count). */
    public static function sectionsWithPools(Test $test): Collection
    {
        $sections = $test->sections()->get();
        $pools = TestQuestion::where('test_id', $test->id)
            ->whereNotNull('section_id')
            ->selectRaw('section_id, count(*) as c')
            ->groupBy('section_id')->pluck('c', 'section_id');
        foreach ($sections as $s) {
            $s->pool_count = (int) ($pools[$s->id] ?? 0);
        }
        return $sections;
    }

    /** Total marks of a section test = Σ question_count × marks_per_question. */
    public static function totalMarks(Test $test): int
    {
        return (int) $test->sections()->get()->sum(fn ($s) => $s->maxMarks());
    }

    /**
     * Publish-time validation. Returns human-readable errors (empty = OK).
     * Enforces every business rule the section engine relies on.
     */
    public static function validate(Test $test): array
    {
        $errors = [];
        $sections = self::sectionsWithPools($test);
        if ($sections->isEmpty()) {
            return ['Add at least one section, or turn off "Section-wise exam".'];
        }
        foreach ($sections as $s) {
            $name = trim((string) $s->title) !== '' ? $s->title : 'Section ' . ($s->order + 1);
            if (trim((string) $s->title) === '') {
                $errors[] = "{$name} needs a name.";
            }
            if ((int) $s->question_count < 1) {
                $errors[] = "{$name} must have at least 1 question.";
            }
            if ((int) $s->marks_per_question < 1) {
                $errors[] = "{$name}: marks per question must be at least 1.";
            }
            if ($s->pool_count < (int) $s->question_count) {
                $errors[] = "{$name} requires {$s->question_count} questions, but only {$s->pool_count} questions are available.";
            }
            $max = $s->maxMarks();
            if ($s->qualifying_marks < 0 || $s->qualifying_marks > $max) {
                $errors[] = "{$name}: qualifying marks must be between 0 and {$max}.";
            }
            if ($test->timer_mode === 'SECTION' && (int) $s->duration_minutes < 1) {
                $errors[] = "{$name}: set a section time limit (section-wise timer is on).";
            }
        }
        $total = self::totalMarks($test);
        if ($test->passing_marks > $total) {
            $errors[] = "Overall qualifying marks ({$test->passing_marks}) cannot exceed total marks ({$total}).";
        }
        return $errors;
    }

    // ------------------------------------------------------------------ paper

    /**
     * Build the frozen paper for a new attempt.
     * @return array{questions: int[], options: object, sections: array}
     */
    public static function buildPaper(Test $test): array
    {
        $sections = $test->sections()->get();
        $tqs = TestQuestion::with('question.options')
            ->where('test_id', $test->id)->whereNotNull('section_id')
            ->orderBy('order')->get()->groupBy('section_id');

        $all = [];
        $optionOrder = [];
        $frozen = [];
        foreach ($sections as $s) {
            $pool = ($tqs[$s->id] ?? collect())->values();
            $need = min((int) $s->question_count, $pool->count());
            $pick = $s->selection_method === 'SEQUENTIAL' ? $pool->take($need) : $pool->shuffle()->take($need);
            $pick = $pick->values();
            if ($s->shuffle_questions) {
                $pick = $pick->shuffle()->values();
            }
            $qids = $pick->pluck('question_id')->map('intval')->all();
            if ($s->shuffle_options) {
                foreach ($pick as $tq) {
                    $opts = $tq->question->options;
                    if ($opts->count()) {
                        $ids = $opts->pluck('id')->map('intval')->all();
                        shuffle($ids);
                        $optionOrder[(string) $tq->question_id] = $ids;
                    }
                }
            }
            $frozen[] = [
                'id' => $s->id,
                'title' => $s->title,
                'qids' => $qids,
                'mpq' => (int) $s->marks_per_question,
                'qual' => (float) $s->qualifying_marks,
                'neg' => (float) $s->negative_marks,
                'mandatory' => (bool) $s->is_mandatory,
                'dur' => (int) ($s->duration_minutes ?? 0),
            ];
            $all = array_merge($all, $qids);
        }
        return ['questions' => $all, 'options' => (object) $optionOrder, 'sections' => $frozen];
    }

    /** Initial navigation/timer state for a fresh attempt. */
    public static function initialState(array $paper, Carbon $now): array
    {
        $first = $paper['sections'][0]['id'] ?? null;
        return [
            'idx' => 0,                                   // current / furthest-unlocked section index
            'started' => $first !== null ? [(string) $first => $now->toIso8601String()] : [],
            'locked' => [],                               // section ids the candidate can no longer enter
        ];
    }

    /** Overall exam length in minutes for a section test. */
    public static function durationMinutes(Test $test): int
    {
        if ($test->timer_mode === 'SECTION') {
            $sum = (int) $test->sections()->sum('duration_minutes');
            return $sum > 0 ? $sum : (int) $test->duration_minutes;
        }
        return (int) $test->duration_minutes;
    }

    // ------------------------------------------------------------------ timers / navigation

    /**
     * Server-authoritative section clock. In SECTION timer mode, expired sections
     * are locked and the clock moves to the next one (continuously, so no time
     * is gained). Returns null when the whole exam is over (caller must submit).
     *
     * @return array{state: array, current: int, remainingMs: ?int}|null
     */
    public static function syncTimers(Attempt $attempt, Test $test): ?array
    {
        $paper = $attempt->question_order ?? [];
        $sections = $paper['sections'] ?? [];
        $state = $attempt->section_state ?? ['idx' => 0, 'started' => [], 'locked' => []];
        if (! $sections) {
            return ['state' => $state, 'current' => 0, 'remainingMs' => null];
        }
        if ($test->timer_mode !== 'SECTION') {
            return ['state' => $state, 'current' => min((int) ($state['idx'] ?? 0), count($sections) - 1), 'remainingMs' => null];
        }

        $now = now();
        $idx = (int) ($state['idx'] ?? 0);
        $changed = false;
        while ($idx < count($sections)) {
            $sec = $sections[$idx];
            $sid = (string) $sec['id'];
            $startedAt = isset($state['started'][$sid]) ? Carbon::parse($state['started'][$sid]) : $now;
            if (! isset($state['started'][$sid])) {
                $state['started'][$sid] = $startedAt->toIso8601String();
                $changed = true;
            }
            $expiry = $startedAt->copy()->addMinutes(max(1, (int) $sec['dur']));
            if ($now->lt($expiry)) {
                if ($changed) {
                    $attempt->update(['section_state' => $state]);
                }
                return ['state' => $state, 'current' => $idx, 'remainingMs' => $expiry->getTimestamp() * 1000 - $now->getTimestamp() * 1000];
            }
            // Expired: lock and move on; the next section starts exactly at this expiry.
            $state['locked'][] = (int) $sec['id'];
            $state['locked'] = array_values(array_unique($state['locked']));
            $idx++;
            $state['idx'] = $idx;
            if ($idx < count($sections)) {
                $state['started'][(string) $sections[$idx]['id']] = $expiry->toIso8601String();
            }
            $changed = true;
        }
        $attempt->update(['section_state' => $state]);
        return null; // every section is over
    }

    /**
     * Candidate finishes the current section early (or the client timer fired).
     * Locks it (in SECTION timer mode, or SEQUENTIAL without return) and opens the next.
     * @return bool false when there is no next section (caller should submit)
     */
    public static function advance(Attempt $attempt, Test $test): bool
    {
        $sections = $attempt->question_order['sections'] ?? [];
        $state = $attempt->section_state ?? ['idx' => 0, 'started' => [], 'locked' => []];
        $idx = (int) ($state['idx'] ?? 0);
        if (! $sections || $idx >= count($sections) - 1) {
            if ($sections && $idx < count($sections)) {
                $state['locked'][] = (int) $sections[$idx]['id'];
                $state['locked'] = array_values(array_unique($state['locked']));
                $state['idx'] = count($sections);
                $attempt->update(['section_state' => $state]);
            }
            return false;
        }
        $lock = $test->timer_mode === 'SECTION' || ($test->section_navigation === 'SEQUENTIAL' && ! $test->allow_section_return);
        if ($lock) {
            $state['locked'][] = (int) $sections[$idx]['id'];
            $state['locked'] = array_values(array_unique($state['locked']));
        }
        $idx++;
        $state['idx'] = $idx;
        $state['started'][(string) $sections[$idx]['id']] = now()->toIso8601String();
        $attempt->update(['section_state' => $state]);
        return true;
    }

    /** Which frozen sections the candidate may currently open (by index => bool). */
    public static function accessibleSections(Attempt $attempt, Test $test): array
    {
        $sections = $attempt->question_order['sections'] ?? [];
        $state = $attempt->section_state ?? ['idx' => 0, 'locked' => []];
        $locked = array_map('intval', $state['locked'] ?? []);
        $idx = (int) ($state['idx'] ?? 0);
        $out = [];
        foreach ($sections as $i => $sec) {
            if (in_array((int) $sec['id'], $locked, true)) {
                $out[$i] = false;
                continue;
            }
            if ($test->timer_mode === 'SECTION') {
                $out[$i] = $i === $idx;                 // only the live section
            } elseif ($test->section_navigation === 'SEQUENTIAL') {
                $out[$i] = $i <= $idx;                  // unlocked so far
            } else {
                $out[$i] = true;                        // free movement
            }
        }
        return $out;
    }

    /** Is a question currently writable (its section open)? Used by auto-save. */
    public static function questionWritable(Attempt $attempt, Test $test, int $questionId): bool
    {
        $sections = $attempt->question_order['sections'] ?? [];
        if (! $sections) {
            return true;
        }
        $access = self::accessibleSections($attempt, $test);
        foreach ($sections as $i => $sec) {
            if (in_array($questionId, array_map('intval', $sec['qids']), true)) {
                return (bool) ($access[$i] ?? false);
            }
        }
        return false;
    }

    // ------------------------------------------------------------------ scoring

    /** Did the candidate actually attempt this answer row? */
    public static function attempted(?AttemptAnswer $a): bool
    {
        if (! $a) {
            return false;
        }
        return count($a->selected_option_ids ?? []) > 0
            || ($a->text_answer !== null && trim($a->text_answer) !== '')
            || $a->numeric_answer !== null
            || $a->bool_answer !== null;
    }

    /**
     * Section-wise evaluation from the stored (graded) answers. Persists one
     * AttemptSectionResult per section and returns the attempt's final outcome.
     * Passed = every mandatory section qualified AND overall qualifying met.
     * If any descriptive answer is still ungraded, the outcome stays pending.
     *
     * @return array{pending: bool, total: float, max: float, passed: ?bool, reason: ?string}
     */
    public static function score(Attempt $attempt, Test $test): array
    {
        $sections = $attempt->question_order['sections'] ?? [];
        $answers = AttemptAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id');
        $descIds = TestQuestion::where('test_id', $test->id)
            ->whereHas('question', fn ($q) => $q->where('type', 'DESCRIPTIVE'))
            ->pluck('question_id')->map('intval')->all();

        $pending = false;
        $total = 0.0;
        $max = 0.0;
        $failedSections = [];
        $order = 0;

        foreach ($sections as $sec) {
            $correct = $wrong = $unanswered = 0;
            $score = 0.0;
            $secPending = false;
            foreach ($sec['qids'] as $qid) {
                $qid = (int) $qid;
                $a = $answers->get($qid);
                if (! self::attempted($a)) {
                    $unanswered++;
                    continue;
                }
                if (in_array($qid, $descIds, true) && ! $a->graded) {
                    $secPending = true;
                    continue;
                }
                $awarded = (float) ($a->awarded_marks ?? 0);
                $score += $awarded;
                if ($a->is_correct === true || (in_array($qid, $descIds, true) && $awarded > 0)) {
                    $correct++;
                } else {
                    $wrong++;
                }
            }
            $secMax = count($sec['qids']) * (int) $sec['mpq'];
            $qual = (float) $sec['qual'];
            $passed = $secPending ? null : ($score >= $qual);
            $pending = $pending || $secPending;

            AttemptSectionResult::updateOrCreate(
                ['attempt_id' => $attempt->id, 'section_id' => $sec['id']],
                [
                    'section_title' => $sec['title'],
                    'order' => $order++,
                    'correct' => $correct, 'wrong' => $wrong, 'unanswered' => $unanswered,
                    'score' => $score, 'max_score' => $secMax,
                    'qualifying_marks' => $qual,
                    'is_mandatory' => (bool) ($sec['mandatory'] ?? true),
                    'passed' => $passed,
                ]
            );
            if ($passed === false && ($sec['mandatory'] ?? true)) {
                $failedSections[] = $sec['title'];
            }
            $total += $score;
            $max += $secMax;
        }

        $reason = null;
        $passed = null;
        if (! $pending) {
            $overallOk = $total >= (float) $test->passing_marks;
            $passed = empty($failedSections) && $overallOk;
            $parts = [];
            if ($failedSections) {
                $parts[] = implode(', ', $failedSections) . ' section qualifying marks not achieved.';
            }
            if (! $overallOk) {
                $parts[] = 'Overall qualifying marks (' . self::fmt($test->passing_marks) . ' / ' . self::fmt($max) . ') not achieved.';
            }
            $reason = $parts ? implode(' ', $parts) : null;
        }

        return ['pending' => $pending, 'total' => $total, 'max' => $max, 'passed' => $passed, 'reason' => $reason];
    }

    /** 12.0 -> "12", 2.5 -> "2.5" */
    public static function fmt($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }

    // ------------------------------------------------------------------ analytics

    /**
     * Section analytics for a test (admin): avg, pass rate, high/low, qualified count.
     * @return array<int, array{section: TestSection, evaluated: int, avg: ?float, passRate: ?int, failRate: ?int, high: ?float, low: ?float, qualified: int, max: int}>
     */
    public static function analytics(Test $test): array
    {
        $sections = $test->sections()->get();
        $rows = AttemptSectionResult::whereIn('section_id', $sections->pluck('id'))
            ->whereNotNull('passed')
            ->whereHas('attempt', fn ($a) => $a->where('status', 'EVALUATED'))
            ->get()->groupBy('section_id');
        $out = [];
        foreach ($sections as $s) {
            $r = $rows[$s->id] ?? collect();
            $n = $r->count();
            $passRate = $n ? (int) round($r->where('passed', true)->count() / $n * 100) : null;
            $out[] = [
                'section' => $s,
                'evaluated' => $n,
                'avg' => $n ? round($r->avg('score'), 1) : null,
                'passRate' => $passRate,
                'failRate' => $passRate === null ? null : 100 - $passRate,
                'high' => $n ? $r->max('score') : null,
                'low' => $n ? $r->min('score') : null,
                'qualified' => $r->where('passed', true)->count(),
                'max' => $s->maxMarks(),
            ];
        }
        return $out;
    }
}
