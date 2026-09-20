<?php

namespace App\Http\Controllers;

use App\Models\AttemptAnswer;
use App\Services\AttemptService;
use Illuminate\Http\Request;

class GradingController extends Controller
{
    public function index()
    {
        // Ungraded descriptive answers in submitted attempts.
        $pending = AttemptAnswer::with(['question', 'attempt.test', 'attempt.candidate'])
            ->where('graded', false)
            ->whereHas('question', fn ($q) => $q->where('type', 'DESCRIPTIVE'))
            ->whereHas('attempt', fn ($a) => $a->whereIn('status', ['SUBMITTED', 'AUTO_SUBMITTED']))
            ->get();

        return view('grading.index', compact('pending'));
    }

    public function grade(Request $request, AttemptAnswer $answer)
    {
        $data = $request->validate([
            'awarded_marks' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);
        AttemptService::gradeDescriptive($answer->id, (float) $data['awarded_marks'], $data['feedback'] ?? null, $request->user()->id);
        return back()->with('status', 'Answer graded.');
    }
}
