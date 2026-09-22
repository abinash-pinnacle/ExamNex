<?php

namespace App\Http\Controllers;

use App\Models\Test;
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
        $attempts = $test->attempts()->with('candidate')->orderByDesc('total_score')->get();

        $evaluated = $attempts->where('status', 'EVALUATED');
        $summary = [
            'total' => $attempts->count(),
            'evaluated' => $evaluated->count(),
            'passed' => $evaluated->where('passed', true)->count(),
            'avg' => $evaluated->count() ? round($evaluated->avg('total_score'), 2) : null,
            'high' => $evaluated->max('total_score'),
            'low' => $evaluated->count() ? $evaluated->min('total_score') : null,
        ];

        return view('reports.show', compact('test', 'attempts', 'summary'));
    }

    public function export(Test $test)
    {
        $attempts = $test->attempts()->with('candidate')->orderByDesc('total_score')->get();
        $filename = 'report-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($test->title)) . '.xlsx';

        $headers = ['Candidate', 'Email', 'Student ID', 'Batch', 'Status', 'Auto Score', 'Manual Score', 'Total Score', 'Max Score', 'Result', 'Terminated', 'Submitted At', 'Violations'];
        $rows = [];
        foreach ($attempts as $a) {
            $rows[] = [
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
                $a->terminated ? 'YES (malpractice)' : 'No',
                $a->submitted_at?->toDateTimeString(),
                $a->violations,
            ];
        }

        return Spreadsheet::download($filename, $headers, $rows);
    }
}
