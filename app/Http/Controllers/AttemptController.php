<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Test;
use App\Services\AttemptService;
use App\Services\SectionService;
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

        // ---- Section-wise exam: current section, access map, per-section clock ----
        $sections = null;
        $currentSection = 0;
        $sectionRemainingMs = null;
        if (! empty($attempt->question_order['sections'])) {
            $sync = SectionService::syncTimers($attempt, $test);
            if ($sync === null) {
                AttemptService::submit($attempt->id, $request->user()->id, true);
                return redirect()->route('attempt.result', $attempt->id)->withErrors(['exam' => 'Section time is over; your attempt was submitted.']);
            }
            $attempt->refresh();
            $currentSection = $sync['current'];
            $sectionRemainingMs = $sync['remainingMs'];
            $access = SectionService::accessibleSections($attempt, $test);
            $locked = array_map('intval', $attempt->section_state['locked'] ?? []);
            $sections = [];
            foreach ($attempt->question_order['sections'] as $i => $sec) {
                $sections[] = [
                    'id' => (int) $sec['id'],
                    'title' => $sec['title'],
                    'qids' => array_map('intval', $sec['qids']),
                    'mpq' => (int) $sec['mpq'],
                    'qual' => (float) $sec['qual'],
                    'neg' => (float) $sec['neg'],
                    'dur' => (int) ($sec['dur'] ?? 0),
                    'accessible' => (bool) ($access[$i] ?? false),
                    'locked' => in_array((int) $sec['id'], $locked, true),
                ];
            }
        }

        // Never cache the live exam: after submit, pressing Back must hit the
        // server again (status is no longer IN_PROGRESS -> redirect to result)
        // instead of restoring the old exam from the browser/bfcache.
        return response()
            ->view('candidate.run', compact('attempt', 'test', 'paper', 'answers', 'remainingMs', 'sections', 'currentSection', 'sectionRemainingMs'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
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

    /** Section tests: candidate ends the current section and opens the next one. */
    public function section(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->candidate_id === $request->user()->id, 403);
        if ($attempt->status !== 'IN_PROGRESS') {
            return redirect()->route('attempt.result', $attempt->id);
        }
        $res = AttemptService::advanceSection($attempt->id, $request->user()->id);
        if ($res['ended']) {
            return redirect()->route('attempt.result', $attempt->id)->with('status', 'Attempt submitted.');
        }
        return redirect()->route('attempt.run', $attempt->id);
    }

    public function event(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->candidate_id === $request->user()->id, 403);
        $res = AttemptService::logExamEvent($attempt->id, $request->user()->id);
        return response()->json(['ok' => true, 'terminated' => $res['terminated'] ?? false]);
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
        $attempt->load(['test', 'sectionResults']);
        $test = $attempt->test;

        $showScore = $attempt->status === 'EVALUATED' && $test->result_visibility === 'IMMEDIATE';
        $paper = null;
        if ($showScore) {
            $paper = $this->buildPaper($attempt);
            $attempt->load('answers');
        }
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $sectionResults = $attempt->sectionResults;

        return view('candidate.result', compact('attempt', 'test', 'showScore', 'paper', 'answers', 'sectionResults'));
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
