<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Test;
use App\Services\SectionService;
use App\Support\Spreadsheet;

class ReportController extends Controller
{
    public function index()
    {
        $tests = Test::where('status', '!=', 'DRAFT')->withCount('attempts')->latest()->get();
        // Attach a per-test result summary (test-wise results at a glance).
        $tests->load(['attempts' => fn ($q) => $q->select('id', 'test_id', 'status', 'passed', 'total_score')]);
        foreach ($tests as $t) {
            $ev = $t->attempts->where('status', 'EVALUATED');
            $t->res_evaluated = $ev->count();
            $t->res_passed = $ev->where('passed', true)->count();
            $t->res_failed = $ev->where('passed', false)->count();
            $t->res_avg = $ev->count() ? round($ev->avg('total_score'), 1) : null;
            $t->res_passrate = $ev->count() ? (int) round($t->res_passed / $ev->count() * 100) : null;
        }
        return view('reports.index', compact('tests'));
    }

    public function show(Test $test)
    {
        $attempts = $test->attempts()->with(['candidate', 'sectionResults'])->orderByDesc('total_score')->get();

        $evaluated = $attempts->where('status', 'EVALUATED');
        $summary = [
            'total' => $attempts->count(),
            'evaluated' => $evaluated->count(),
            'passed' => $evaluated->where('passed', true)->count(),
            'avg' => $evaluated->count() ? round($evaluated->avg('total_score'), 2) : null,
            'high' => $evaluated->max('total_score'),
            'low' => $evaluated->count() ? $evaluated->min('total_score') : null,
        ];

        // Section-wise columns + analytics (section tests only).
        $sections = $test->usesSections() ? $test->sections()->get() : collect();
        $sectionAnalytics = $sections->isNotEmpty() ? SectionService::analytics($test) : [];

        return view('reports.show', compact('test', 'attempts', 'summary', 'sections', 'sectionAnalytics'));
    }

    /** One candidate's attempt in detail: section performance + answer counts. */
    public function attempt(Test $test, Attempt $attempt)
    {
        abort_unless($attempt->test_id === $test->id, 404);
        $attempt->load(['candidate', 'sectionResults', 'answers']);

        $qids = array_map('intval', $attempt->question_order['questions'] ?? []);
        $questions = Question::whereIn('id', $qids)->get()->keyBy('id');
        $answers = $attempt->answers->keyBy('question_id');

        $correct = $wrong = $unanswered = 0;
        foreach ($qids as $qid) {
            $a = $answers->get($qid);
            if (! SectionService::attempted($a)) {
                $unanswered++;
            } elseif ($a->is_correct === true || ($questions->get($qid)?->type === 'DESCRIPTIVE' && ($a->awarded_marks ?? 0) > 0)) {
                $correct++;
            } else {
                $wrong++;
            }
        }
        $counts = compact('correct', 'wrong', 'unanswered');

        return view('reports.attempt', compact('test', 'attempt', 'counts'));
    }

    public function export(Test $test)
    {
        $attempts = $test->attempts()->with(['candidate', 'sectionResults'])->orderByDesc('total_score')->get();
        $filename = 'report-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($test->title)) . '.xlsx';

        $sections = $test->usesSections() ? $test->sections()->get() : collect();

        $headers = ['Candidate', 'Email', 'Student ID', 'Batch', 'Status', 'Auto Score', 'Manual Score', 'Total Score', 'Max Score', 'Result', 'Reason', 'Terminated', 'Submitted At', 'Violations'];
        foreach ($sections as $s) {
            $headers[] = $s->title . ' (score / ' . $s->maxMarks() . ')';
            $headers[] = $s->title . ' result';
        }
        $rows = [];
        foreach ($attempts as $a) {
            $row = [
                $a->candidate?->name,
                $a->candidate?->email,
                $a->candidate?->student_id,
                $a->candidate?->batch,
                $a->status,
                $a->auto_score,
                $a->manual_score,
                $a->total_score,
                $a->max_score ?? $test->total_marks,
                $a->passed === null ? '' : ($a->passed ? 'PASS' : 'FAIL'),
                $a->result_reason,
                $a->terminated ? 'YES (malpractice)' : 'No',
                $a->submitted_at?->toDateTimeString(),
                $a->violations,
            ];
            $bySection = $a->sectionResults->keyBy('section_id');
            foreach ($sections as $s) {
                $r = $bySection->get($s->id);
                $row[] = $r ? $r->score : '';
                $row[] = $r ? ($r->passed === null ? 'PENDING' : ($r->passed ? 'PASS' : 'FAIL')) : '';
            }
            $rows[] = $row;
        }

        return Spreadsheet::download($filename, $headers, $rows);
    }
}
