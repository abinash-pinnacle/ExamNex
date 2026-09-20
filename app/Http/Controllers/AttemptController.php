<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Test;
use App\Services\AttemptService;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function start(Request $request, Test $test)
    {
        $res = AttemptService::start($test, $request->user(), $request->boolean('system_check', true));
        if (! $res['ok']) {
            if (isset($res['attemptId'])) {
                return redirect()->route('attempt.result', $res['attemptId'])->withErrors(['exam' => $res['error']]);
            }
            return back()->withErrors(['exam' => $res['error']]);
        }
        return redirect()->route('attempt.run', $res['attemptId']);
    }

    public function run(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->candidate_id === $request->user()->id, 403);

        if ($attempt->status !== 'IN_PROGRESS') {
            return redirect()->route('attempt.result', $attempt->id);
        }
        // Server-authoritative clock: expired -> auto submit.
        if ($attempt->deadline_at->isPast()) {
            AttemptService::submit($attempt->id, $request->user()->id, true);
            return redirect()->route('attempt.result', $attempt->id)->withErrors(['exam' => 'Time expired; your attempt was submitted.']);
        }

        $test = $attempt->test;
        $paper = $this->buildPaper($attempt);
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $remainingMs = $attempt->deadline_at->getTimestamp() * 1000 - now()->getTimestamp() * 1000;

        return view('candidate.run', compact('attempt', 'test', 'paper', 'answers', 'remainingMs'));
    }

    public function save(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->candidate_id === $request->user()->id, 403);
        $data = $request->validate([
            'question_id' => 'required|integer',
            'selected_option_ids' => 'nullable|array',
            'text_answer' => 'nullable|string',
            'numeric_answer' => 'nullable|numeric',
            'bool_answer' => 'nullable|boolean',
            'marked_for_review' => 'nullable|boolean',
        ]);
        $res = AttemptService::saveAnswer($attempt->id, $request->user()->id, $data);
        return response()->json($res);
    }

    public function event(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->candidate_id === $request->user()->id, 403);
        AttemptService::logExamEvent($attempt->id, $request->user()->id);
        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->candidate_id === $request->user()->id, 403);
        AttemptService::submit($attempt->id, $request->user()->id, $request->boolean('auto'), $request->boolean('terminated'));

        // Optional redirect after submission (not for terminated attempts).
        $url = $attempt->test->redirect_url;
        if ($url && ! $request->boolean('terminated')) {
            return redirect()->away($url);
        }
        return redirect()->route('attempt.result', $attempt->id)->with('status', 'Attempt submitted.');
    }

    public function result(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->candidate_id === $request->user()->id, 403);
        $attempt->load('test');
        $test = $attempt->test;

        $showScore = $attempt->status === 'EVALUATED' && $test->result_visibility !== 'HIDDEN';
        $paper = null;
        if ($showScore && $test->result_visibility === 'IMMEDIATE') {
            $paper = $this->buildPaper($attempt);
            $attempt->load('answers');
        }
        $answers = $attempt->answers()->get()->keyBy('question_id');

        return view('candidate.result', compact('attempt', 'test', 'showScore', 'paper', 'answers'));
    }

    public function certificate(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->candidate_id === $request->user()->id, 403);
        abort_unless($attempt->status === 'EVALUATED' && $attempt->passed && $attempt->test->issue_certificate, 403, 'No certificate available.');
        $attempt->load(['test', 'candidate']);
        return view('candidate.certificate', ['attempt' => $attempt]);
    }

    /**
     * Rebuild the ordered paper from the frozen question_order so a resume shows
     * the identical layout (questions + option order).
     * @return array<int,array{question: Question, options: \Illuminate\Support\Collection}>
     */
    private function buildPaper(Attempt $attempt): array
    {
        $order = $attempt->question_order ?? [];
        $qIds = $order['questions'] ?? [];
        $optOrder = $order['options'] ?? [];

        $questions = Question::with('options')->whereIn('id', $qIds)->get()->keyBy('id');

        $paper = [];
        foreach ($qIds as $qid) {
            $q = $questions->get($qid);
            if (! $q) {
                continue;
            }
            $options = $q->options;
            if (isset($optOrder[(string) $qid])) {
                $byId = $options->keyBy('id');
                $options = collect($optOrder[(string) $qid])->map(fn ($oid) => $byId->get($oid))->filter()->values();
            }
            $paper[] = ['question' => $q, 'options' => $options];
        }
        return $paper;
    }
}
