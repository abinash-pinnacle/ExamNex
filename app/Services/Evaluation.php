<?php

namespace App\Services;

use App\Models\Question;
use App\Models\AttemptAnswer;

/**
 * Auto-grading for objective questions. Ported 1:1 from evaluation.ts.
 * DESCRIPTIVE questions are not auto-gradable and route to manual grading.
 */
class Evaluation
{
    private static function norm(string $s): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtolower($s)));
    }

    /**
     * @return array{autoGradable: bool, isCorrect: ?bool, awardedMarks: ?float}
     */
    public static function grade(Question $q, ?AttemptAnswer $a, bool $negativeMarkingOn, ?int $effectiveMarks = null, ?float $negativeOverride = null): array
    {
        $marks = $effectiveMarks ?? $q->marks;

        if ($q->type === 'DESCRIPTIVE') {
            return ['autoGradable' => false, 'isCorrect' => null, 'awardedMarks' => null];
        }

        $selected = $a?->selected_option_ids ?? [];
        $attempted = $a && (
            count($selected) > 0 ||
            ($a->text_answer !== null && trim($a->text_answer) !== '') ||
            $a->numeric_answer !== null ||
            $a->bool_answer !== null
        );

        if (! $attempted) {
            // Unattempted: no penalty.
            return ['autoGradable' => true, 'isCorrect' => false, 'awardedMarks' => 0.0];
        }

        $correct = false;
        switch ($q->type) {
            case 'MCQ_SINGLE': {
                $chosen = array_map('strval', $selected);
                $correctIds = $q->options->where('is_correct', true)->pluck('id')->map('strval')->all();
                $correct = count($chosen) === 1 && in_array($chosen[0], $correctIds, true);
                break;
            }
            case 'MCQ_MULTI': {
                $chosen = array_unique(array_map('strval', $selected));
                $correctIds = $q->options->where('is_correct', true)->pluck('id')->map('strval')->all();
                sort($chosen);
                sort($correctIds);
                $correct = $chosen === $correctIds && count($correctIds) > 0;
                break;
            }
            case 'MCQ_BLANKS': {
                // Correct only when EVERY blank's selected option is that blank's correct one.
                $chosen = array_map('intval', $selected);
                $groups = $q->options->groupBy('option_group');
                $correct = $groups->count() > 0;
                foreach ($groups as $opts) {
                    $correctId = (int) optional($opts->firstWhere('is_correct', true))->id;
                    $selInGroup = $opts->pluck('id')->map('intval')->intersect($chosen)->values();
                    if ($selInGroup->count() !== 1 || (int) $selInGroup->first() !== $correctId) {
                        $correct = false;
                        break;
                    }
                }
                break;
            }
            case 'TRUE_FALSE':
                $correct = $a->bool_answer !== null && $a->bool_answer === (bool) $q->bool_answer;
                break;
            case 'FILL_BLANK': {
                $given = self::norm($a->text_answer ?? '');
                $accepted = array_map(fn ($s) => self::norm($s), explode('|', $q->correct_text ?? ''));
                $correct = $given !== '' && in_array($given, $accepted, true);
                break;
            }
            case 'NUMERIC': {
                if ($a->numeric_answer !== null && $q->numeric_answer !== null) {
                    $tol = $q->numeric_tolerance ?? 0;
                    $correct = abs($a->numeric_answer - $q->numeric_answer) <= $tol;
                }
                break;
            }
        }

        $penalty = ($negativeOverride !== null && $negativeOverride > 0) ? $negativeOverride : (float) $q->negative_marks;
        $awarded = $correct ? (float) $marks : ($negativeMarkingOn ? -abs($penalty) : 0.0);
        return ['autoGradable' => true, 'isCorrect' => $correct, 'awardedMarks' => $awarded];
    }
}
