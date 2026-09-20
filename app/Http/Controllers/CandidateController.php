<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Test;
use Illuminate\Support\Facades\DB;

class CandidateController extends Controller
{
    public function home()
    {
        $user = auth()->user();

        $assignedTestIds = DB::table('test_assignments')->where('user_id', $user->id)->pluck('test_id');
        $attempts = Attempt::where('candidate_id', $user->id)->get()->keyBy('test_id');

        // A student only sees tests they have NOT completed yet. Once submitted /
        // auto-submitted / evaluated, the test disappears from their list — results
        // are visible only to Admin/Test Creator (Reports). In-progress stays (Resume).
        $completedTestIds = $attempts
            ->filter(fn ($a) => in_array($a->status, ['SUBMITTED', 'AUTO_SUBMITTED', 'EVALUATED'], true))
            ->keys()->all();

        $tests = Test::whereIn('id', $assignedTestIds)
            ->where('status', 'PUBLISHED')
            ->whereNotIn('id', $completedTestIds)
            ->withCount('testQuestions')
            ->get();

        // If exactly one pending test, take the candidate straight to it (no list step).
        if ($tests->count() === 1) {
            return redirect()->route('candidate.intro', $tests->first()->id);
        }

        return view('candidate.home', compact('tests', 'attempts'));
    }

    /** Pre-exam intro / system-check page. */
    public function intro(Test $test)
    {
        $user = auth()->user();
        $assigned = $test->assignments()->where('user_id', $user->id)->exists();
        abort_unless($assigned, 403, 'You are not assigned to this test.');

        $test->loadCount('testQuestions');
        $attempt = Attempt::where('test_id', $test->id)->where('candidate_id', $user->id)->latest('started_at')->first();
        $priorCount = Attempt::where('test_id', $test->id)->where('candidate_id', $user->id)->count();

        return view('candidate.intro', compact('test', 'attempt', 'priorCount'));
    }
}
