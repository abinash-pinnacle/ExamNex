<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    private function makeAccessCode(int $len = 10): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $s = '';
        for ($i = 0; $i < $len; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $s;
    }

    private function recomputeTotal(int $testId): int
    {
        $total = TestQuestion::with('question:id,marks')->where('test_id', $testId)->get()
            ->sum(fn ($tq) => $tq->marks_override ?? $tq->question->marks);
        Test::where('id', $testId)->update(['total_marks' => $total]);
        return $total;
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'title' => 'required|string|min:2|max:200',
            'description' => 'nullable|string|max:2000',
            'subject' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:120',
            'audience' => 'nullable|string|max:120',
            'duration_minutes' => 'required|integer|min:1',
            'total_marks' => 'nullable|integer|min:0',
            'passing_marks' => 'required|integer|min:0',
            'negative_marks' => 'nullable|numeric|min:0|max:100',
            'max_attempts' => 'required|integer|min:1',
            'max_candidates' => 'required|integer|min:1|max:100000',
            'result_visibility' => 'required|in:IMMEDIATE,AFTER_REVIEW,HIDDEN',
            'question_order' => 'required|in:SHUFFLE,SEQUENTIAL',
            'instructions' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'grace_minutes' => 'nullable|integer|min:0|max:1440',
            'completion_message' => 'nullable|string|max:1000',
            'redirect_url' => 'nullable|url|max:255',
            'access_password' => 'nullable|string|max:100',
        ]);
        // total_marks is auto-recomputed from questions; keep any provided as a hint.
        unset($d['total_marks']);
        // Keep an existing password when the field is left blank (don't wipe it).
        if (empty($d['access_password'])) {
            unset($d['access_password']);
        }
        return $d;
    }

    private const BOOL_OPTS = [
        'negative_marking_on', 'shuffle_options', 'issue_certificate',
        'one_per_page', 'allow_review', 'autosave', 'full_screen', 'prevent_tab_switch',
        'detect_copy', 'show_warning', 'restrict_right_click', 'webcam_proctoring',
        'browser_lockdown', 'ai_cheating_detection', 'show_solution', 'allow_download_result',
        'email_notification', 'feedback_form', 'public_access', 'require_registration',
    ];

    private function boolFields(Request $request): array
    {
        $out = [];
        foreach (self::BOOL_OPTS as $f) {
            $out[$f] = $request->boolean($f);
        }
        // Question order select drives shuffle_questions.
        $out['shuffle_questions'] = $request->input('question_order') === 'SHUFFLE';
        return $out;
    }

    public function index()
    {
        $tests = Test::where('is_template', false)->withCount(['testQuestions', 'attempts'])->latest()->paginate(15);
        return view('tests.index', compact('tests'));
    }

    public function create(Request $request)
    {
        $templates = Test::where('is_template', true)->latest()->get();
        $prefill = $request->filled('template')
            ? Test::where('is_template', true)->find($request->get('template'))
            : null;
        return view('tests.form', ['test' => null, 'prefill' => $prefill, 'templates' => $templates]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        $b = $this->boolFields($request);
        $test = Test::create(array_merge($d, $b, [
            'status' => 'DRAFT',
            'access_code' => $b['public_access'] ? $this->makeAccessCode() : null,
            'created_by' => $request->user()->id,
        ]));
        Audit::log('test.create', ['entity' => 'Test', 'entity_id' => $test->id]);
        return redirect()->route('tests.show', $test)->with('status', 'Test created. Now add questions.');
    }

    public function storeTemplate(Request $request)
    {
        $d = $this->validated($request);
        $b = $this->boolFields($request);
        $test = Test::create(array_merge($d, $b, [
            'status' => 'DRAFT', 'is_template' => true, 'created_by' => $request->user()->id,
        ]));
        Audit::log('test.saveTemplate', ['entity' => 'Test', 'entity_id' => $test->id]);
        return redirect()->route('tests.create')->with('status', 'Saved as template: ' . $test->title);
    }

    public function edit(Test $test)
    {
        if ($test->status === 'PUBLISHED') {
            return redirect()->route('tests.show', $test)->withErrors(['test' => 'Published tests cannot be edited.']);
        }
        return view('tests.form', compact('test'));
    }

    public function update(Request $request, Test $test)
    {
        if ($test->status === 'PUBLISHED') {
            return back()->withErrors(['test' => 'Published tests cannot be edited.']);
        }
        $d = $this->validated($request);
        $b = $this->boolFields($request);
        // Keep an existing code so a shared link survives a toggle; mint on first enable.
        $accessCode = $b['public_access'] ? ($test->access_code ?? $this->makeAccessCode()) : $test->access_code;
        $test->update(array_merge($d, $b, ['access_code' => $accessCode]));
        Audit::log('test.update', ['entity' => 'Test', 'entity_id' => $test->id]);
        return redirect()->route('tests.show', $test)->with('status', 'Test updated.');
    }

    public function show(Test $test)
    {
        $test->load(['testQuestions.question', 'assignments.user']);
        $available = Question::where('status', 'ACTIVE')
            ->whereNotIn('id', $test->testQuestions->pluck('question_id'))
            ->latest()->limit(50)->get();
        $candidates = User::where('role', 'CANDIDATE')->where('is_active', true)->orderBy('name')->get();
        $publicUrl = $test->public_access && $test->access_code
            ? url("/test/{$test->access_code}") : null;

        return view('tests.show', compact('test', 'available', 'candidates', 'publicUrl'));
    }

    public function addQuestions(Request $request, Test $test)
    {
        $ids = (array) $request->input('question_ids', []);
        DB::transaction(function () use ($ids, $test) {
            $order = TestQuestion::where('test_id', $test->id)->count();
            foreach ($ids as $qid) {
                TestQuestion::firstOrCreate(
                    ['test_id' => $test->id, 'question_id' => $qid],
                    ['order' => $order++]
                );
            }
            $this->recomputeTotal($test->id);
        });
        Audit::log('test.addQuestions', ['entity' => 'Test', 'entity_id' => $test->id, 'detail' => ['count' => count($ids)]]);
        return back()->with('status', count($ids) . ' question(s) added.');
    }

    public function removeQuestion(Test $test, Question $question)
    {
        TestQuestion::where('test_id', $test->id)->where('question_id', $question->id)->delete();
        $this->recomputeTotal($test->id);
        return back()->with('status', 'Question removed.');
    }

    public function assign(Request $request, Test $test)
    {
        $userIds = (array) $request->input('user_ids', []);
        $batch = trim($request->input('batch', ''));

        $query = User::where('role', 'CANDIDATE')->where('is_active', true);
        $query->where(function ($q) use ($userIds, $batch) {
            if ($batch !== '') {
                $q->orWhere('batch', $batch);
            }
            if ($userIds) {
                $q->orWhereIn('id', $userIds);
            }
        });
        if (! $userIds && $batch === '') {
            return back()->withErrors(['assign' => 'Pick candidates or enter a batch.']);
        }

        $count = 0;
        foreach ($query->get() as $u) {
            $test->assignments()->firstOrCreate(['user_id' => $u->id], ['assigned_at' => now()]);
            $count++;
        }
        Audit::log('test.assign', ['entity' => 'Test', 'entity_id' => $test->id, 'detail' => ['count' => $count]]);
        return back()->with('status', "{$count} candidate(s) assigned.");
    }

    public function publish(Test $test)
    {
        if ($test->testQuestions()->count() === 0) {
            return back()->withErrors(['test' => 'Add at least one question first.']);
        }
        $this->recomputeTotal($test->id);
        $test->update([
            'status' => 'PUBLISHED',
            'access_code' => $test->public_access && ! $test->access_code ? $this->makeAccessCode() : $test->access_code,
        ]);
        Audit::log('test.publish', ['entity' => 'Test', 'entity_id' => $test->id]);
        return back()->with('status', 'Test published.');
    }

    public function archive(Test $test)
    {
        $test->update(['status' => 'ARCHIVED']);
        Audit::log('test.archive', ['entity' => 'Test', 'entity_id' => $test->id]);
        return back()->with('status', 'Test archived.');
    }

    public function monitor(Test $test)
    {
        $test->loadCount('testQuestions');
        $totalQ = $test->test_questions_count;

        $assignments = $test->assignments()->with('user')->get();
        // Latest attempt per candidate.
        $attempts = $test->attempts()->orderBy('started_at')->get()->keyBy('candidate_id');

        // How many questions each candidate has saved an answer for.
        $answered = DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->where('attempts.test_id', $test->id)
            ->groupBy('attempts.candidate_id')
            ->selectRaw('attempts.candidate_id as cid, count(*) as c')
            ->pluck('c', 'cid');

        $rows = $assignments->map(function ($a) use ($attempts, $answered, $totalQ) {
            $att = $attempts->get($a->user_id);
            return (object) [
                'user' => $a->user,
                'attempt' => $att,
                'status' => $att ? $att->status : 'NOT_STARTED',
                'answered' => (int) ($answered[$a->user_id] ?? 0),
                'total' => $totalQ,
            ];
        })->sortByDesc(fn ($r) => $r->attempt?->started_at?->timestamp ?? 0)->values();

        $summary = [
            'assigned' => $assignments->count(),
            'not_started' => $rows->where('status', 'NOT_STARTED')->count(),
            'in_progress' => $rows->where('status', 'IN_PROGRESS')->count(),
            'completed' => $rows->whereIn('status', ['SUBMITTED', 'AUTO_SUBMITTED', 'EVALUATED'])->count(),
            'flagged' => $rows->filter(fn ($r) => $r->attempt && ($r->attempt->terminated || $r->attempt->violations > 0))->count(),
        ];

        return view('tests.monitor', compact('test', 'rows', 'summary', 'totalQ'));
    }
}
