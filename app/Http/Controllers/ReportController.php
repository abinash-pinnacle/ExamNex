<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Support\Spreadsheet;

class ReportController extends Controller
{
    public function index()
    {
        $tests = Test::withCount('attempts')->where('status', '!=', 'DRAFT')->latest()->get();
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
