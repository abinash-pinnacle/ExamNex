<?php

namespace App\Http\Controllers;

use App\Services\Gemini;
use App\Services\QuestionService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function form()
    {
        return view('questions.ai', ['generated' => session('ai_generated'), 'params' => session('ai_params')]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'topic' => 'required|string|max:200',
            'count' => 'required|integer|min:1|max:50',
            'type' => 'required|in:MCQ_SINGLE,TRUE_FALSE,FILL_BLANK,DESCRIPTIVE',
            'difficulty' => 'required|in:EASY,MEDIUM,HARD,MIXED',
            'folder' => 'required|string|max:80',
            'subject' => 'required|string|max:80',
            'topic_name' => 'required|string|max:80',
        ]);

        $res = Gemini::generate([
            'topic' => $data['topic'],
            'count' => $data['count'],
            'type' => $data['type'],
            'difficulty' => $data['difficulty'],
        ]);

        if (! $res['ok']) {
            return back()->withInput()->withErrors(['ai' => $res['error']]);
        }

        return redirect()->route('questions.ai')->with([
            'ai_generated' => $res['questions'],
            'ai_params' => [
                'type' => $data['type'],
                'folder' => $data['folder'],
                'subject' => $data['subject'],
                'topic_name' => $data['topic_name'],
            ],
        ]);
    }

    public function import(Request $request)
    {
        $questions = json_decode($request->input('questions', '[]'), true);
        $type = $request->input('type');
        $folder = $request->input('folder');
        $subject = $request->input('subject');
        $topic = $request->input('topic_name');

        if (! is_array($questions) || ! $questions) {
            return back()->withErrors(['ai' => 'Nothing to import.']);
        }

        $rows = Gemini::toImportRows($questions, $type, $folder, $subject, $topic);
        // Same import pipeline as CSV — tenant-free but identical dedup + hierarchy.
        $result = QuestionService::import($rows, $request->user()->id);

        return redirect()->route('questions.index')->with(
            'status',
            "AI import: {$result['created']} added, {$result['duplicates']} duplicate(s) skipped, {$result['invalid']} invalid."
        );
    }
}
