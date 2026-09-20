<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Minimal Gemini client for authoring exam questions. Server-side only.
 * Config: GEMINI_API_KEY (required), GEMINI_MODEL (optional).
 * Ported from ai/gemini.ts.
 */
class Gemini
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models';

    private const TYPE_LABEL = [
        'MCQ_SINGLE'  => 'multiple-choice (single correct answer)',
        'TRUE_FALSE'  => 'true/false',
        'FILL_BLANK'  => 'fill-in-the-blank',
        'DESCRIPTIVE' => 'descriptive/long-answer',
    ];

    private static function responseSchema(string $type): array
    {
        $difficulty = ['type' => 'STRING', 'enum' => ['EASY', 'MEDIUM', 'HARD']];
        $explanation = ['type' => 'STRING'];

        if ($type === 'MCQ_SINGLE') {
            $props = [
                'question' => ['type' => 'STRING'],
                'options' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'correctIndex' => ['type' => 'INTEGER'],
                'explanation' => $explanation,
                'difficulty' => $difficulty,
            ];
            $required = ['question', 'options', 'correctIndex'];
        } elseif ($type === 'TRUE_FALSE') {
            $props = [
                'question' => ['type' => 'STRING'],
                'answer' => ['type' => 'STRING', 'enum' => ['True', 'False']],
                'explanation' => $explanation,
                'difficulty' => $difficulty,
            ];
            $required = ['question', 'answer'];
        } else {
            $props = [
                'question' => ['type' => 'STRING'],
                'answer' => ['type' => 'STRING'],
                'explanation' => $explanation,
                'difficulty' => $difficulty,
            ];
            $required = ['question', 'answer'];
        }

        return ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => $props, 'required' => $required]];
    }

    private static function buildPrompt(array $o, array $avoid): string
    {
        $lines = [
            "You are an expert exam question author. Write exactly {$o['count']} " . self::TYPE_LABEL[$o['type']] . " question(s) on the topic: \"{$o['topic']}\".",
            $o['difficulty'] === 'MIXED'
                ? 'Vary the difficulty across EASY, MEDIUM and HARD.'
                : "Target difficulty: {$o['difficulty']}.",
            'Every question must be factually correct, self-contained, unambiguous and appropriate for a formal test.',
        ];
        if ($o['type'] === 'MCQ_SINGLE') {
            $lines[] = "Give exactly 4 distinct answer options with only ONE correct. 'correctIndex' is the 0-based index of the correct option. Make the wrong options plausible, not obviously silly.";
        } elseif ($o['type'] === 'FILL_BLANK') {
            $lines[] = "Use '___' to mark the blank in the question. 'answer' is the exact word/phrase that fills it.";
        } elseif ($o['type'] === 'DESCRIPTIVE') {
            $lines[] = "'answer' is a concise model answer a grader can compare against.";
        }
        $lines[] = "Add a one-line 'explanation' for each. Do not number the questions.";
        if ($avoid) {
            $lines[] = "Do NOT repeat or rephrase any of these already-generated questions:\n"
                . implode("\n", array_map(fn ($a) => "- {$a}", array_slice($avoid, 0, 60)));
        }
        $lines[] = 'Return ONLY JSON matching the provided schema.';
        return implode("\n", $lines);
    }

    private static function normalise(array $items, string $type): array
    {
        $out = [];
        foreach ($items as $it) {
            $text = isset($it['question']) && is_string($it['question']) ? trim($it['question']) : '';
            if ($text === '') {
                continue;
            }
            $diff = isset($it['difficulty']) && is_string($it['difficulty']) && in_array(strtoupper($it['difficulty']), ['EASY', 'MEDIUM', 'HARD'], true)
                ? strtoupper($it['difficulty']) : null;
            $explanation = isset($it['explanation']) && is_string($it['explanation']) ? trim($it['explanation']) : null;

            if ($type === 'MCQ_SINGLE') {
                $options = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $it['options'] ?? []), fn ($s) => $s !== ''));
                if (count($options) < 2) {
                    continue;
                }
                $ci = isset($it['correctIndex']) ? (int) $it['correctIndex'] : 0;
                if ($ci < 0 || $ci >= count($options)) {
                    $ci = 0;
                }
                $out[] = ['text' => $text, 'options' => $options, 'correctIndex' => $ci, 'explanation' => $explanation, 'difficulty' => $diff];
            } elseif ($type === 'TRUE_FALSE') {
                $ans = str_starts_with(strtolower(trim((string) ($it['answer'] ?? ''))), 't') ? 'True' : 'False';
                $out[] = ['text' => $text, 'answer' => $ans, 'explanation' => $explanation, 'difficulty' => $diff];
            } else {
                $ans = isset($it['answer']) && is_string($it['answer']) ? trim($it['answer']) : '';
                if ($ans === '') {
                    continue;
                }
                $out[] = ['text' => $text, 'answer' => $ans, 'explanation' => $explanation, 'difficulty' => $diff];
            }
        }
        return $out;
    }

    /** @return array{ok: bool, items?: array, error?: string} */
    private static function call(string $prompt, string $type): array
    {
        $key = config('services.gemini.key');
        if (! $key) {
            return ['ok' => false, 'error' => 'Gemini API key not configured. Add GEMINI_API_KEY to the .env file.'];
        }
        $model = config('services.gemini.model') ?: 'gemini-3.6-flash';
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => self::responseSchema($type),
                'temperature' => 0.7,
                'maxOutputTokens' => 8192,
            ],
        ];

        $res = null;
        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                $res = Http::timeout(60)->withHeaders(['Content-Type' => 'application/json'])
                    ->post(self::ENDPOINT . "/{$model}:generateContent?key={$key}", $payload);
            } catch (\Throwable $e) {
                if ($attempt === 3) {
                    return ['ok' => false, 'error' => "Could not reach Gemini. Check the server's internet connection."];
                }
                usleep(800000 * ($attempt + 1));
                continue;
            }
            if (in_array($res->status(), [503, 429], true)) {
                if ($attempt === 3) {
                    break;
                }
                usleep(1200000 * ($attempt + 1));
                continue;
            }
            break;
        }
        if (! $res) {
            return ['ok' => false, 'error' => 'Could not reach Gemini. Try again.'];
        }

        if (! $res->successful()) {
            $detail = $res->json('error.message', '');
            if ($res->status() === 401 || ($res->status() === 400 && preg_match('/API key|credential/i', $detail)))
                return ['ok' => false, 'error' => 'Invalid Gemini API key. Create a key at https://aistudio.google.com/apikey (it starts with "AIza") and set GEMINI_API_KEY in .env.'];
            if ($res->status() === 429) return ['ok' => false, 'error' => 'Gemini rate limit hit — wait a moment and try again.'];
            if ($res->status() === 503) return ['ok' => false, 'error' => 'Gemini is busy right now — please try again in a minute.'];
            if ($res->status() === 404) return ['ok' => false, 'error' => "Model unavailable: {$detail} Set GEMINI_MODEL to a current model."];
            return ['ok' => false, 'error' => "Gemini error ({$res->status()})" . ($detail ? ": {$detail}" : '')];
        }

        $d = $res->json();
        if (! empty($d['promptFeedback']['blockReason'])) {
            return ['ok' => false, 'error' => "Request blocked by Gemini's safety filter — try a different topic."];
        }
        $raw = '';
        foreach ($d['candidates'][0]['content']['parts'] ?? [] as $p) {
            $raw .= $p['text'] ?? '';
        }
        if (trim($raw) === '') {
            return ['ok' => false, 'error' => 'Gemini returned nothing. Try again or reduce the count.'];
        }
        $parsed = json_decode($raw, true);
        if (! is_array($parsed)) {
            return ['ok' => false, 'error' => "Could not parse Gemini's JSON — try again."];
        }
        return ['ok' => true, 'items' => $parsed];
    }

    /**
     * Generate up to `count` questions.
     * @return array{ok: bool, questions?: array, error?: string}
     */
    public static function generate(array $o): array
    {
        $CHUNK = 12;
        $target = max(1, min(50, (int) $o['count']));
        $collected = [];
        $avoid = [];
        $lastError = '';

        while (count($collected) < $target) {
            $need = min($CHUNK, $target - count($collected));
            $r = self::call(self::buildPrompt(array_merge($o, ['count' => $need]), $avoid), $o['type']);
            if (! $r['ok']) {
                if ($collected) {
                    break;
                }
                return ['ok' => false, 'error' => $r['error']];
            }
            $batch = self::normalise($r['items'], $o['type']);
            if (! $batch) {
                $lastError = 'Gemini produced no usable questions.';
                break;
            }
            foreach ($batch as $q) {
                if (count($collected) >= $target) {
                    break;
                }
                $collected[] = $q;
                $avoid[] = $q['text'];
            }
            if (count($batch) < $need) {
                break;
            }
        }

        if (! $collected) {
            return ['ok' => false, 'error' => $lastError ?: 'No questions generated.'];
        }
        return ['ok' => true, 'questions' => $collected];
    }

    /** Map AI-drafted questions to import rows (routed through QuestionService::import). */
    public static function toImportRows(array $questions, string $type, string $folder, string $subject, string $topic): array
    {
        $rows = [];
        foreach ($questions as $q) {
            $row = [
                'folder' => $folder, 'subject' => $subject, 'topic' => $topic,
                'type' => $type, 'question' => $q['text'],
                'difficulty' => $q['difficulty'] ?? '', 'explanation' => $q['explanation'] ?? '',
            ];
            if ($type === 'MCQ_SINGLE') {
                foreach (($q['options'] ?? []) as $i => $opt) {
                    $row['option' . ($i + 1)] = $opt;
                }
                $row['correct'] = (string) (($q['correctIndex'] ?? 0) + 1);
            } elseif ($type === 'TRUE_FALSE') {
                $row['correct'] = $q['answer'] ?? 'True';
            } elseif ($type === 'FILL_BLANK') {
                $row['correct'] = $q['answer'] ?? '';
            } elseif ($type === 'DESCRIPTIVE') {
                $row['modelanswer'] = $q['answer'] ?? '';
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
