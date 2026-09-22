<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Question;
use App\Services\QuestionBank;
use App\Services\QuestionService;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $q = Question::query()->with('topicRef.subject.folder')->latest();

        if ($s = trim($request->get('search', ''))) {
            $q->where('text', 'like', "%{$s}%");
        }
        if ($request->filled('type')) {
            $q->where('type', $request->get('type'));
        }
        if ($request->filled('difficulty')) {
            $q->where('difficulty', $request->get('difficulty'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->get('status'));
        }
        if ($request->filled('topic_id')) {
            $q->where('topic_id', $request->get('topic_id'));
        } elseif ($request->filled('subject')) {
            $q->where('subject', $request->get('subject'));
        } elseif ($request->filled('category')) {
            $q->where('category', $request->get('category'));
        }

        $questions = $q->paginate(15)->withQueryString();
        $folders = Folder::with([
            'subjects' => fn ($s) => $s->orderBy('name'),
            'subjects.topics' => fn ($t) => $t->orderBy('name')->withCount('questions'),
        ])->orderBy('name')->get();
        $totalQuestions = Question::count();

        return view('questions.index', compact('questions', 'folders', 'totalQuestions'));
    }

    public function create()
    {
        $folders = Folder::with('subjects.topics')->orderBy('name')->get();
        return view('questions.form', ['question' => null, 'folders' => $folders]);
    }

    public function edit(Question $question)
    {
        $question->load('options');
        $folders = Folder::with('subjects.topics')->orderBy('name')->get();
        return view('questions.form', compact('question', 'folders'));
    }

    public function store(Request $request)
    {
        $this->validateQuestion($request);
        $input = $this->buildInput($request);
        $input['imagePath'] = $this->resolveImage($request, null);
        $res = QuestionService::create($input, $request->user()->id);
        return $this->respond($res, 'Question created.');
    }

    public function update(Request $request, Question $question)
    {
        $this->validateQuestion($request);
        $input = $this->buildInput($request);
        $input['imagePath'] = $this->resolveImage($request, $question);
        $res = QuestionService::update($question->id, $input, $request->user()->id);
        return $this->respond($res, 'Question updated.');
    }

    /**
     * Resolve the question image: a freshly uploaded file (stored under
     * public/uploads/questions with a server-detected extension), the existing
     * image when unchanged, or null when the user ticked "remove".
     */
    private function resolveImage(Request $request, ?Question $existing): ?string
    {
        if ($request->boolean('remove_image')) {
            return null;
        }
        if ($request->hasFile('image')) {
            $dir = public_path('uploads/questions');
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $ext = $request->file('image')->extension() ?: 'png'; // server-detected, never the client extension
            $name = 'q-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $request->file('image')->move($dir, $name);
            return '/uploads/questions/' . $name;
        }
        return $existing?->image_path;
    }

    /** Reject blank / symbol-only text and unknown question types before saving. */
    private function validateQuestion(Request $request): void
    {
        $request->validate([
            'text' => 'required|string|max:5000',
            'type' => ['required', \Illuminate\Validation\Rule::in(['MCQ_SINGLE', 'MCQ_MULTI', 'TRUE_FALSE', 'FILL_BLANK', 'NUMERIC', 'DESCRIPTIVE'])],
            // Optional question image (e.g. a reasoning/puzzle diagram). No SVG (stored-XSS).
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp,gif|max:4096',
        ], [], ['text' => 'question text']);

        if (\App\Support\Normalizer::questionText((string) $request->input('text')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'text' => 'Question text cannot be blank or symbols only.',
            ]);
        }
    }

    public function destroy(Question $question)
    {
        $res = QuestionService::delete($question->id);
        if (! $res['ok']) {
            return back()->withErrors(['question' => $res['error']]);
        }
        return redirect()->route('questions.index')->with('status', 'Question deleted.');
    }

    /** AJAX duplicate pre-check. */
    public function checkDuplicate(Request $request)
    {
        return response()->json(['duplicate' => QuestionService::checkDuplicate($request->input('text', ''))]);
    }

    // ---- CSV import ----
    public function importForm()
    {
        return view('questions.import', ['result' => null]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'folder' => 'nullable|string',
            'subject' => 'nullable|string',
            'topic' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        try {
            $rows = \App\Support\Spreadsheet::read($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Could not read the file. Make sure it is a valid Excel (.xlsx) or CSV file.']);
        }
        if (empty($rows)) {
            return back()->withErrors(['file' => 'The file is empty or could not be read.']);
        }

        $defaults = array_filter([
            'folder' => trim($request->get('folder', '')),
            'subject' => trim($request->get('subject', '')),
            'topic' => trim($request->get('topic', '')),
        ]);

        $result = QuestionService::import($rows, $request->user()->id, $defaults);
        return view('questions.import', ['result' => $result]);
    }

    /** Download a ready-to-fill Excel template with headers + example rows. */
    public function template()
    {
        $headers = ['folder', 'subject', 'topic', 'type', 'question',
            'option1', 'option2', 'option3', 'option4', 'correct',
            'difficulty', 'marks', 'negative', 'tolerance', 'explanation', 'modelanswer', 'image'];

        $rows = [
            ['General Knowledge', 'Science', 'Physics', 'mcq', 'What is the SI unit of force?', 'Newton', 'Joule', 'Watt', 'Pascal', '1', 'EASY', '1', '0', '', 'Force is measured in Newtons.', '', ''],
            ['General Knowledge', 'Science', 'Physics', 'multi', 'Which are vector quantities?', 'Velocity', 'Speed', 'Acceleration', 'Mass', '1,3', 'MEDIUM', '2', '0', '', '', '', ''],
            ['General Knowledge', 'Science', 'Physics', 'truefalse', 'Light travels faster than sound.', '', '', '', '', 'true', 'EASY', '1', '0', '', '', '', ''],
            ['General Knowledge', 'Maths', 'Basics', 'numeric', 'How many metres in 2 km?', '', '', '', '', '2000', 'EASY', '1', '0', '0', '', '', ''],
            ['Reasoning', 'Puzzles', 'Number Series', 'mcq', 'Which number replaces the question mark?', '18', '19', '20', '21', '2', 'MEDIUM', '3', '0', '', '', 'https://example.com/puzzle1.png'],
            ['General Knowledge', 'Science', 'Physics', 'descriptive', "State Newton's first law.", '', '', '', '', '', 'HARD', '5', '0', '', '', 'An object stays at rest or uniform motion unless a net force acts.', ''],
        ];

        return \App\Support\Spreadsheet::download('examnex-question-template.xlsx', $headers, $rows);
    }

    /** Map form fields to a QuestionService input array. */
    private function buildInput(Request $request): array
    {
        // Resolve topic: either topic_id, or folder/subject/topic names.
        $topicId = $request->input('topic_id');
        if (! $topicId) {
            $f = trim($request->input('folder', ''));
            $s = trim($request->input('subject', ''));
            $t = trim($request->input('topic', ''));
            if ($f && $s && $t) {
                $topicId = QuestionBank::ensureTopicPath($f, $s, $t, $request->user()->id);
            }
        }

        $type = $request->input('type', 'MCQ_SINGLE');
        $input = [
            'topicId' => $topicId,
            'type' => $type,
            'text' => $request->input('text', ''),
            'difficulty' => $request->input('difficulty') ?: null,
            'status' => $request->input('status', 'ACTIVE'),
            'marks' => (int) $request->input('marks', 1),
            'negativeMarks' => (float) $request->input('negative_marks', 0),
            'explanation' => $request->input('explanation') ?: null,
        ];

        if ($type === 'MCQ_SINGLE' || $type === 'MCQ_MULTI') {
            $texts = $request->input('option_text', []);
            $correct = (array) $request->input('option_correct', []);
            $options = [];
            foreach ($texts as $i => $text) {
                if (trim((string) $text) === '') {
                    continue;
                }
                $options[] = [
                    'text' => $text,
                    'isCorrect' => in_array((string) $i, array_map('strval', $correct), true),
                ];
            }
            $input['options'] = $options;
        } elseif ($type === 'TRUE_FALSE') {
            $b = $request->input('bool_answer');
            $input['boolAnswer'] = $b === null ? null : ($b === '1' || $b === 'true' || $b === 1);
        } elseif ($type === 'FILL_BLANK') {
            $input['correctText'] = $request->input('correct_text');
        } elseif ($type === 'NUMERIC') {
            $na = $request->input('numeric_answer');
            $input['numericAnswer'] = ($na === null || $na === '') ? null : (float) $na;
            $input['numericTolerance'] = (float) $request->input('numeric_tolerance', 0);
        } elseif ($type === 'DESCRIPTIVE') {
            $input['modelAnswer'] = $request->input('model_answer');
        }

        return $input;
    }

    private function respond(array $res, string $ok)
    {
        if ($res['ok']) {
            return redirect()->route('questions.index')->with('status', $ok);
        }
        $msg = $res['error'];
        if (isset($res['duplicate'])) {
            $d = $res['duplicate'];
            $msg .= " (Found in {$d['folder']} → {$d['subject']} → {$d['topic']})";
        }
        return back()->withInput()->withErrors(['question' => $msg]);
    }
}
