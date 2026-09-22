<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\TestSection;
use App\Models\User;
use App\Services\SectionService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Keep total_marks in sync. Plain tests: sum of the questions on the test.
     * Section tests: Σ question_count × marks_per_question (the pool size is irrelevant).
     */
    private function recomputeTotal(int $testId): int
    {
        $test = Test::find($testId);
        if (! $test) {
            return 0;
        }
        if ($test->usesSections()) {
            $total = SectionService::totalMarks($test);
        } else {
            $total = (int) TestQuestion::with('question:id,marks')->where('test_id', $testId)->get()
                ->sum(fn ($tq) => $tq->marks_override ?? $tq->question->marks);
        }
        $update = ['total_marks' => $total];
        // Percentage-based pass mark: keep passing_marks in sync with the live total.
        // Guarded so the app never breaks if the passing_percent column isn't migrated yet.
        if (Schema::hasColumn('tests', 'passing_percent') && $test->passing_percent !== null) {
            $update['passing_marks'] = (int) ceil($test->passing_percent / 100 * $total);
        }
        Test::where('id', $testId)->update($update);
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
            'passing_mode' => 'nullable|in:percent,marks',
            'passing_marks' => 'nullable|integer|min:0',
            'passing_percent' => 'nullable|integer|min:0|max:100',
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
            // Section-wise exam settings
            'section_navigation' => 'nullable|in:FREE,SEQUENTIAL',
            'timer_mode' => 'nullable|in:OVERALL,SECTION',
        ]);
        // total_marks is auto-recomputed from questions; keep any provided as a hint.
        unset($d['total_marks']);

        // Passing criteria: either a % of total (auto-derived, survives total changes)
        // or a fixed number of marks. passing_mode only drives interpretation; it is not stored.
        $hasPercentCol = Schema::hasColumn('tests', 'passing_percent');
        $mode = $d['passing_mode'] ?? (($d['passing_percent'] ?? null) !== null ? 'percent' : 'marks');
        unset($d['passing_mode']);
        $pct = max(0, min(100, (int) ($d['passing_percent'] ?? 40)));
        $hintTotal = (int) $request->input('total_marks', 0);
        if ($mode === 'percent') {
            // passing_marks is derived from the live total in recomputeTotal(); seed a provisional value.
            $d['passing_marks'] = (int) ceil($pct / 100 * $hintTotal);
            if ($hasPercentCol) {
                $d['passing_percent'] = $pct;
            } else {
                unset($d['passing_percent']);
            }
        } else {
            $d['passing_marks'] = (int) ($d['passing_marks'] ?? 0);
            if ($hasPercentCol) {
                $d['passing_percent'] = null;
            } else {
                unset($d['passing_percent']);
            }
        }

        // Section settings (guarded so the app keeps working before the migration runs).
        if (Schema::hasColumn('tests', 'use_sections')) {
            $d['section_navigation'] = $d['section_navigation'] ?? 'FREE';
            $d['timer_mode'] = $d['timer_mode'] ?? 'OVERALL';
        } else {
            unset($d['section_navigation'], $d['timer_mode']);
        }

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
        if (Schema::hasColumn('tests', 'use_sections')) {
            $out['use_sections'] = $request->boolean('use_sections');
            $out['allow_section_return'] = $request->boolean('allow_section_return', true);
        }
        return $out;
    }

    // ---------------------------------------------------------------- sections

    /** Clean the section builder input (sections[i][field]). */
    private function sectionsInput(Request $request): array
    {
        $raw = $request->validate([
            'sections' => 'nullable|array|max:50',
            'sections.*.id' => 'nullable|integer',
            'sections.*.title' => 'nullable|string|max:150',
            'sections.*.description' => 'nullable|string|max:1000',
            'sections.*.question_count' => 'nullable|integer|min:0|max:1000',
            'sections.*.marks_per_question' => 'nullable|integer|min:1|max:100',
            'sections.*.qualifying_marks' => 'nullable|numeric|min:0',
            'sections.*.negative_marks' => 'nullable|numeric|min:0|max:100',
            'sections.*.duration_minutes' => 'nullable|integer|min:0|max:1440',
            'sections.*.selection_method' => 'nullable|in:RANDOM,SEQUENTIAL',
            'sections.*.shuffle_questions' => 'nullable|boolean',
            'sections.*.shuffle_options' => 'nullable|boolean',
            'sections.*.is_mandatory' => 'nullable|boolean',
        ])['sections'] ?? [];

        $out = [];
        foreach (array_values($raw) as $i => $s) {
            $title = trim((string) ($s['title'] ?? ''));
            if ($title === '' && (int) ($s['question_count'] ?? 0) === 0) {
                continue; // blank row from the builder
            }
            $out[] = [
                'id' => isset($s['id']) ? (int) $s['id'] : null,
                'title' => $title !== '' ? $title : 'Section ' . ($i + 1),
                'description' => $s['description'] ?? null,
                'order' => $i,
                'question_count' => (int) ($s['question_count'] ?? 0),
                'marks_per_question' => max(1, (int) ($s['marks_per_question'] ?? 1)),
                'qualifying_marks' => (float) ($s['qualifying_marks'] ?? 0),
                'negative_marks' => (float) ($s['negative_marks'] ?? 0),
                'duration_minutes' => isset($s['duration_minutes']) && $s['duration_minutes'] !== '' ? (int) $s['duration_minutes'] : null,
                'selection_method' => $s['selection_method'] ?? 'RANDOM',
                'shuffle_questions' => (bool) ($s['shuffle_questions'] ?? true),
                'shuffle_options' => (bool) ($s['shuffle_options'] ?? true),
                'is_mandatory' => (bool) ($s['is_mandatory'] ?? true),
            ];
        }
        return $out;
    }

    /** Sync the builder rows to test_sections: update by id, create new, delete removed. */
    private function saveSections(Test $test, array $rows): void
    {
        if (! Schema::hasColumn('test_sections', 'question_count')) {
            return;
        }
        $existing = $test->sections()->get()->keyBy('id');
        $keep = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            unset($row['id']);
            if ($id && $existing->has($id)) {
                $existing[$id]->update($row);
                $keep[] = $id;
            } else {
                $sec = $test->sections()->create($row);
                $keep[] = $sec->id;
            }
        }
        // Removed sections: their pooled questions go back to "unassigned" (FK nullOnDelete).
        $test->sections()->whereNotIn('id', $keep)->delete();
    }

    /** Copy the sections of a template onto a new test (question pools are not copied). */
    private function copySections(Test $from, Test $to): void
    {
        foreach ($from->sections as $s) {
            $to->sections()->create(collect($s->toArray())->except(['id', 'test_id', 'created_at', 'updated_at'])->all());
        }
    }

    // ---------------------------------------------------------------- CRUD

    public function index()
    {
        $tests = Test::where('is_template', false)->withCount(['testQuestions', 'attempts', 'sections'])->latest()->paginate(15);
        return view('tests.index', compact('tests'));
    }

    public function create(Request $request)
    {
        $templates = Test::where('is_template', true)->latest()->get();
        $prefill = $request->filled('template')
            ? Test::where('is_template', true)->with('sections')->find($request->get('template'))
            : null;
        return view('tests.form', ['test' => null, 'prefill' => $prefill, 'templates' => $templates]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        $b = $this->boolFields($request);
        $sections = $this->sectionsInput($request);
        $test = Test::create(array_merge($d, $b, [
            'status' => 'DRAFT',
            'access_code' => $b['public_access'] ? $this->makeAccessCode() : null,
            'created_by' => $request->user()->id,
        ]));
        $this->saveSections($test, $sections);
        $this->recomputeTotal($test->id);
        Audit::log('test.create', ['entity' => 'Test', 'entity_id' => $test->id]);
        return redirect()->route('tests.show', $test)->with('status', $test->usesSections()
            ? 'Test created. Now add questions to each section.'
            : 'Test created. Now add questions.');
    }

    public function storeTemplate(Request $request)
    {
        $d = $this->validated($request);
        $b = $this->boolFields($request);
        $sections = $this->sectionsInput($request);
        $test = Test::create(array_merge($d, $b, [
            'status' => 'DRAFT', 'is_template' => true, 'created_by' => $request->user()->id,
        ]));
        $this->saveSections($test, $sections);
        Audit::log('test.saveTemplate', ['entity' => 'Test', 'entity_id' => $test->id]);
        return redirect()->route('tests.create')->with('status', 'Saved as template: ' . $test->title);
    }

    public function edit(Test $test)
    {
        if ($test->status === 'PUBLISHED') {
            return redirect()->route('tests.show', $test)->withErrors(['test' => 'Published tests cannot be edited.']);
        }
        $test->load('sections');
        return view('tests.form', compact('test'));
    }

    public function update(Request $request, Test $test)
    {
        if ($test->status === 'PUBLISHED') {
            return back()->withErrors(['test' => 'Published tests cannot be edited.']);
        }
        $d = $this->validated($request);
        $b = $this->boolFields($request);
        $sections = $this->sectionsInput($request);
        // Keep an existing code so a shared link survives a toggle; mint on first enable.
        $accessCode = $b['public_access'] ? ($test->access_code ?? $this->makeAccessCode()) : $test->access_code;
        $test->update(array_merge($d, $b, ['access_code' => $accessCode]));
        $this->saveSections($test, $sections);
        // Re-sync a percentage pass mark / section totals against the current questions.
        $this->recomputeTotal($test->id);
        Audit::log('test.update', ['entity' => 'Test', 'entity_id' => $test->id]);
        return redirect()->route('tests.show', $test)->with('status', 'Test updated.');
    }

    public function show(Request $request, Test $test)
    {
        $test->load(['testQuestions.question', 'testQuestions.section', 'assignments.user']);
        $candidates = User::where('role', 'CANDIDATE')->where('is_active', true)->orderBy('name')->get();
        $publicUrl = $test->public_access && $test->access_code
            ? url("/test/{$test->access_code}") : null;

        // ---- Section summary + readiness ----
        $sections = $test->use_sections ? SectionService::sectionsWithPools($test) : collect();
        $sectionErrors = $test->usesSections() ? SectionService::validate($test) : [];

        // ---- Question bank picker with filters (subject / topic / difficulty / type / search) ----
        $filters = [
            'search' => trim((string) $request->get('search', '')),
            'subject' => $request->get('subject'),
            'topic' => $request->get('topic'),
            'difficulty' => $request->get('difficulty'),
            'type' => $request->get('type'),
            'section' => $request->get('section'),
        ];
        $q = Question::where('status', 'ACTIVE')->whereNotIn('id', $test->testQuestions->pluck('question_id'));
        if ($filters['search'] !== '') {
            $q->where('text', 'like', '%' . $filters['search'] . '%');
        }
        foreach (['subject', 'topic', 'difficulty', 'type'] as $f) {
            if (! empty($filters[$f])) {
                $q->where($f, $filters[$f]);
            }
        }
        $filterCount = (clone $q)->count();
        $available = $q->latest()->limit(100)->get();

        $subjects = Question::where('status', 'ACTIVE')->whereNotNull('subject')->where('subject', '!=', '')->distinct()->orderBy('subject')->pluck('subject');
        $topics = Question::where('status', 'ACTIVE')->whereNotNull('topic')->where('topic', '!=', '')
            ->when(! empty($filters['subject']), fn ($t) => $t->where('subject', $filters['subject']))
            ->distinct()->orderBy('topic')->pluck('topic');

        return view('tests.show', compact('test', 'available', 'candidates', 'publicUrl',
            'sections', 'sectionErrors', 'filters', 'filterCount', 'subjects', 'topics'));
    }

    public function addQuestions(Request $request, Test $test)
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('question_ids', []))));
        $sectionId = $request->filled('section_id') ? (int) $request->input('section_id') : null;

        if ($test->use_sections) {
            // Section tests: every question must land in a section of THIS test.
            if (! $sectionId || ! $test->sections()->where('id', $sectionId)->exists()) {
                return back()->withErrors(['questions' => 'Choose the section these questions belong to.']);
            }
        } else {
            $sectionId = null;
        }
        if (! $ids) {
            return back()->withErrors(['questions' => 'Select at least one question.']);
        }

        $added = 0;
        DB::transaction(function () use ($ids, $test, $sectionId, &$added) {
            $order = TestQuestion::where('test_id', $test->id)->count();
            foreach ($ids as $qid) {
                // unique(test_id, question_id): a question can sit in only ONE section of a test.
                $tq = TestQuestion::firstOrCreate(
                    ['test_id' => $test->id, 'question_id' => $qid],
                    ['order' => $order++, 'section_id' => $sectionId]
                );
                if ($tq->wasRecentlyCreated) {
                    $added++;
                }
            }
            $this->recomputeTotal($test->id);
        });
        Audit::log('test.addQuestions', ['entity' => 'Test', 'entity_id' => $test->id, 'detail' => ['count' => $added, 'section_id' => $sectionId]]);
        $skipped = count($ids) - $added;
        return redirect()->route('tests.show', [$test, 'section' => $sectionId])
            ->with('status', "{$added} question(s) added." . ($skipped ? " {$skipped} already on this test (a question can be in only one section)." : ''));
    }

    /**
     * Fill a section's pool automatically from the bank using the current filters:
     * picks random ACTIVE questions (not already on the test) until the section has
     * enough available questions for its required count (or the requested number).
     */
    public function autoFillSection(Request $request, Test $test, TestSection $section)
    {
        abort_unless($section->test_id === $test->id, 404);
        $data = $request->validate([
            'count' => 'nullable|integer|min:1|max:1000',
            'subject' => 'nullable|string', 'topic' => 'nullable|string',
            'difficulty' => 'nullable|string', 'type' => 'nullable|string', 'search' => 'nullable|string',
        ]);
        $have = TestQuestion::where('test_id', $test->id)->where('section_id', $section->id)->count();
        $want = (int) ($data['count'] ?? max(0, (int) $section->question_count - $have));
        if ($want < 1) {
            return back()->with('status', "{$section->title} already has enough questions.");
        }
        $q = Question::where('status', 'ACTIVE')->whereNotIn('id', TestQuestion::where('test_id', $test->id)->pluck('question_id'));
        if (! empty($data['search'])) {
            $q->where('text', 'like', '%' . $data['search'] . '%');
        }
        foreach (['subject', 'topic', 'difficulty', 'type'] as $f) {
            if (! empty($data[$f])) {
                $q->where($f, $data[$f]);
            }
        }
        $ids = $q->inRandomOrder()->limit($want)->pluck('id')->all();
        $added = 0;
        DB::transaction(function () use ($ids, $test, $section, &$added) {
            $order = TestQuestion::where('test_id', $test->id)->count();
            foreach ($ids as $qid) {
                $tq = TestQuestion::firstOrCreate(['test_id' => $test->id, 'question_id' => $qid], ['order' => $order++, 'section_id' => $section->id]);
                if ($tq->wasRecentlyCreated) {
                    $added++;
                }
            }
            $this->recomputeTotal($test->id);
        });
        Audit::log('test.autoFillSection', ['entity' => 'Test', 'entity_id' => $test->id, 'detail' => ['section_id' => $section->id, 'count' => $added]]);
        $msg = "{$added} question(s) added to {$section->title}.";
        if ($added < $want) {
            $msg .= ' Only ' . $added . ' matching question(s) were available in the bank.';
        }
        return redirect()->route('tests.show', [$test, 'section' => $section->id])->with('status', $msg);
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
        if ($test->use_sections) {
            // Section tests: every business rule must hold before the test goes live.
            $errors = SectionService::validate($test);
            if ($errors) {
                return back()->withErrors(['test' => $errors]);
            }
        } elseif ($test->testQuestions()->count() === 0) {
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
        // Section tests: the paper size is the sum of required questions, not the pool.
        $totalQ = $test->usesSections() ? (int) $test->sections()->sum('question_count') : $test->test_questions_count;

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
