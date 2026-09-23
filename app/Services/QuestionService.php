<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Support\Audit;
use App\Support\Normalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Question CRUD + bulk import with GLOBAL duplicate prevention.
 * Dedup key: questions.normalized_text (unique index). We check before insert
 * AND catch the unique-violation for concurrency — ported from questions.ts.
 */
class QuestionService
{
    public const DUP_MSG = 'Duplicate Question: This question already exists in the Question Bank.';

    /** Where a duplicate lives, for the UI. */
    public static function locate(int $questionId): array
    {
        $q = Question::with('topicRef.subject.folder')->find($questionId);
        return [
            'questionId' => $questionId,
            'folder' => $q?->topicRef?->subject?->folder?->name ?? '—',
            'subject' => $q?->topicRef?->subject?->name ?? '—',
            'topic' => $q?->topicRef?->name ?? '—',
        ];
    }

    /** Type-specific completeness validation. Returns an error string or null. */
    public static function validateAnswers(array $d): ?string
    {
        $type = $d['type'];
        $options = $d['options'] ?? [];
        if ($type === 'MCQ_SINGLE' || $type === 'MCQ_MULTI') {
            if (count($options) < 2) {
                return 'Add at least two options.';
            }
            $correct = array_filter($options, fn ($o) => ! empty($o['isCorrect']));
            if (count($correct) < 1) {
                return 'Mark at least one correct option.';
            }
            if ($type === 'MCQ_SINGLE' && count($correct) !== 1) {
                return 'Single-answer questions need exactly one correct option.';
            }
        }
        if ($type === 'MCQ_BLANKS') {
            $groups = [];
            foreach ($options as $o) {
                $groups[(int) ($o['group'] ?? 0)][] = $o;
            }
            if (count($groups) < 1) {
                return 'Add at least one blank with its options.';
            }
            foreach ($groups as $gi => $opts) {
                if (count($opts) < 2) {
                    return 'Blank ' . ($gi + 1) . ' needs at least two options.';
                }
                if (count(array_filter($opts, fn ($o) => ! empty($o['isCorrect']))) !== 1) {
                    return 'Blank ' . ($gi + 1) . ' must have exactly one correct option.';
                }
            }
        }
        if ($type === 'FILL_BLANK' && empty(trim($d['correctText'] ?? ''))) {
            return 'Provide the accepted answer(s).';
        }
        if ($type === 'NUMERIC' && ($d['numericAnswer'] ?? null) === null) {
            return 'Provide the numeric answer.';
        }
        if ($type === 'TRUE_FALSE' && ($d['boolAnswer'] ?? null) === null) {
            return 'Select True or False as the answer.';
        }
        return null;
    }

    /** Lightweight duplicate pre-check for the UI (saves nothing). */
    public static function checkDuplicate(string $text): ?array
    {
        $norm = Normalizer::questionText($text);
        if ($norm === '') {
            return null;
        }
        $existing = Question::where('normalized_text', $norm)->first();
        return $existing ? self::locate($existing->id) : null;
    }

    /**
     * @return array{ok: bool, id?: int, error?: string, duplicate?: array}
     */
    public static function create(array $d, int $userId): array
    {
        if ($err = self::validateAnswers($d)) {
            return ['ok' => false, 'error' => $err];
        }
        $norm = Normalizer::questionText($d['text']);

        $path = QuestionBank::getTopicPath((int) $d['topicId']);
        if (! $path) {
            return ['ok' => false, 'error' => 'That topic no longer exists — pick another.'];
        }
        if ($existing = Question::where('normalized_text', $norm)->first()) {
            return ['ok' => false, 'error' => self::DUP_MSG, 'duplicate' => self::locate($existing->id)];
        }

        try {
            $q = DB::transaction(function () use ($d, $norm, $path, $userId) {
                $q = Question::create([
                    'type' => $d['type'],
                    'text' => trim($d['text']),
                    'image_path' => $d['imagePath'] ?? null,
                    'normalized_text' => $norm,
                    'topic_id' => $path['topicId'],
                    'category' => $path['folder'],
                    'subject' => $path['subject'],
                    'topic' => $path['topic'],
                    'difficulty' => $d['difficulty'] ?? null,
                    'status' => $d['status'] ?? 'ACTIVE',
                    'marks' => $d['marks'] ?? 1,
                    'negative_marks' => $d['negativeMarks'] ?? 0,
                    'correct_text' => $d['correctText'] ?? null,
                    'numeric_answer' => $d['numericAnswer'] ?? null,
                    'numeric_tolerance' => $d['numericTolerance'] ?? 0,
                    'bool_answer' => $d['boolAnswer'] ?? null,
                    'model_answer' => $d['modelAnswer'] ?? null,
                    'explanation' => $d['explanation'] ?? null,
                    'created_by' => $userId,
                ]);
                self::syncOptions($q, $d['options'] ?? []);
                return $q;
            });
        } catch (QueryException $e) {
            if (self::isDup($e)) {
                return ['ok' => false, 'error' => self::DUP_MSG];
            }
            throw $e;
        }

        Audit::log('question.create', ['entity' => 'Question', 'entity_id' => $q->id, 'detail' => ['type' => $d['type']]]);
        return ['ok' => true, 'id' => $q->id];
    }

    public static function update(int $id, array $d, int $userId): array
    {
        if ($err = self::validateAnswers($d)) {
            return ['ok' => false, 'error' => $err];
        }
        $norm = Normalizer::questionText($d['text']);
        $path = QuestionBank::getTopicPath((int) $d['topicId']);
        if (! $path) {
            return ['ok' => false, 'error' => 'That topic no longer exists.'];
        }
        if ($clash = Question::where('normalized_text', $norm)->where('id', '!=', $id)->first()) {
            return ['ok' => false, 'error' => self::DUP_MSG, 'duplicate' => self::locate($clash->id)];
        }

        try {
            DB::transaction(function () use ($id, $d, $norm, $path) {
                QuestionOption::where('question_id', $id)->delete();
                $q = Question::findOrFail($id);
                $q->update([
                    'type' => $d['type'],
                    'text' => trim($d['text']),
                    'image_path' => $d['imagePath'] ?? null,
                    'normalized_text' => $norm,
                    'topic_id' => $path['topicId'],
                    'category' => $path['folder'],
                    'subject' => $path['subject'],
                    'topic' => $path['topic'],
                    'difficulty' => $d['difficulty'] ?? null,
                    'status' => $d['status'] ?? $q->status,
                    'marks' => $d['marks'] ?? 1,
                    'negative_marks' => $d['negativeMarks'] ?? 0,
                    'correct_text' => $d['correctText'] ?? null,
                    'numeric_answer' => $d['numericAnswer'] ?? null,
                    'numeric_tolerance' => $d['numericTolerance'] ?? 0,
                    'bool_answer' => $d['boolAnswer'] ?? null,
                    'model_answer' => $d['modelAnswer'] ?? null,
                    'explanation' => $d['explanation'] ?? null,
                ]);
                self::syncOptions($q, $d['options'] ?? []);
            });
        } catch (QueryException $e) {
            if (self::isDup($e)) {
                return ['ok' => false, 'error' => self::DUP_MSG];
            }
            throw $e;
        }

        Audit::log('question.update', ['entity' => 'Question', 'entity_id' => $id]);
        return ['ok' => true, 'id' => $id];
    }

    public static function delete(int $id): array
    {
        $used = DB::table('test_questions')->where('question_id', $id)->count();
        if ($used > 0) {
            return ['ok' => false, 'error' => "Used in {$used} test(s). Archive it instead, or remove it from those tests first."];
        }
        Question::where('id', $id)->delete();
        Audit::log('question.delete', ['entity' => 'Question', 'entity_id' => $id]);
        return ['ok' => true];
    }

    private static function syncOptions(Question $q, array $options): void
    {
        foreach (array_values($options) as $i => $o) {
            QuestionOption::create([
                'question_id' => $q->id,
                'text' => $o['text'],
                'is_correct' => ! empty($o['isCorrect']),
                'order' => $i,
                'option_group' => (int) ($o['group'] ?? 0),
            ]);
        }
    }

    private static function isDup(QueryException $e): bool
    {
        // MySQL duplicate-entry error code.
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    // ---------------- Bulk import (CSV) ----------------

    private static function normKey(string $s): string
    {
        return preg_replace('/[\s_\-]+/', '', mb_strtolower($s));
    }

    /**
     * Accept only a safe image reference for import: an https(s) URL or a local
     * /uploads/... path already on this server. Anything else is ignored (no SVG,
     * no arbitrary local paths) — mirrors the manual-upload restrictions.
     */
    public static function cleanImageRef(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $s) && ! preg_match('#\.svgz?(\?|$)#i', $s)) {
            return $s;
        }
        if (preg_match('#^/uploads/[\w./-]+\.(png|jpe?g|webp|gif)$#i', $s)) {
            return $s;
        }
        return null;
    }

    private static function mapType(string $v): string
    {
        $t = self::normKey($v);
        if (in_array($t, ['mcqmulti', 'multi', 'multiple', 'multiselect', 'mcqmultiple'], true)) return 'MCQ_MULTI';
        if (in_array($t, ['truefalse', 'tf', 'boolean'], true)) return 'TRUE_FALSE';
        if (in_array($t, ['fill', 'fillblank', 'fillintheblank', 'blank'], true)) return 'FILL_BLANK';
        if (in_array($t, ['numeric', 'number', 'num'], true)) return 'NUMERIC';
        if (in_array($t, ['descriptive', 'desc', 'long', 'longanswer', 'subjective'], true)) return 'DESCRIPTIVE';
        return 'MCQ_SINGLE';
    }

    /** Build a question input array from a spreadsheet row. */
    private static function rowToQuestion(array $raw, int $topicId): array
    {
        $row = [];
        foreach ($raw as $k => $v) {
            $row[self::normKey((string) $k)] = $v === null ? '' : trim((string) $v);
        }

        $type = self::mapType($row['type'] ?? 'MCQ_SINGLE');
        $text = $row['question'] ?? $row['text'] ?? $row['questiontext'] ?? '';
        if ($text === '') {
            throw new \RuntimeException('missing question text');
        }

        $opts = [];
        for ($i = 1; $i <= 8; $i++) {
            $val = $row["option{$i}"] ?? $row['option' . chr(96 + $i)] ?? '';
            if ($val !== '') {
                $opts[] = $val;
            }
        }
        $correctRaw = trim($row['correct'] ?? $row['answer'] ?? $row['correctanswer'] ?? '');

        $diff = strtoupper($row['difficulty'] ?? '');
        $base = [
            'topicId' => $topicId,
            'type' => $type,
            'text' => $text,
            'difficulty' => in_array($diff, ['EASY', 'MEDIUM', 'HARD'], true) ? $diff : null,
            'imagePath' => self::cleanImageRef($row['image'] ?? $row['imageurl'] ?? $row['imagepath'] ?? ''),
            'marks' => isset($row['marks']) && $row['marks'] !== '' ? (int) $row['marks'] : 1,
            'negativeMarks' => ($row['negativemarks'] ?? $row['negative'] ?? '') !== '' ? (float) ($row['negativemarks'] ?? $row['negative']) : 0,
            'explanation' => $row['explanation'] ?? null,
        ];

        if ($type === 'MCQ_SINGLE' || $type === 'MCQ_MULTI') {
            if (count($opts) < 2) {
                throw new \RuntimeException('needs at least 2 options');
            }
            $tokens = array_filter(array_map('trim', preg_split('/[,;|]/', $correctRaw)));
            $idx = [];
            foreach ($tokens as $tk) {
                if (preg_match('/^\d+$/', $tk)) {
                    $idx[(int) $tk - 1] = true;
                } elseif (preg_match('/^[a-h]$/i', $tk)) {
                    $idx[ord(strtoupper($tk)) - 65] = true;
                }
            }
            if (count($idx) === 0) {
                throw new \RuntimeException('no correct option specified (e.g. 2 or B or 1,3)');
            }
            $base['options'] = [];
            foreach ($opts as $i => $o) {
                $base['options'][] = ['text' => $o, 'isCorrect' => isset($idx[$i])];
            }
        } elseif ($type === 'TRUE_FALSE') {
            $t = self::normKey($correctRaw);
            if (in_array($t, ['true', 't', '1', 'yes'], true)) $base['boolAnswer'] = true;
            elseif (in_array($t, ['false', 'f', '0', 'no'], true)) $base['boolAnswer'] = false;
            else throw new \RuntimeException('correct must be TRUE or FALSE');
        } elseif ($type === 'FILL_BLANK') {
            if ($correctRaw === '') {
                throw new \RuntimeException('provide the accepted answer(s)');
            }
            $base['correctText'] = $correctRaw;
        } elseif ($type === 'NUMERIC') {
            if ($correctRaw === '' || ! is_numeric($correctRaw)) {
                throw new \RuntimeException('provide a numeric answer');
            }
            $base['numericAnswer'] = (float) $correctRaw;
            $base['numericTolerance'] = ($row['tolerance'] ?? '') !== '' ? (float) $row['tolerance'] : 0;
        } elseif ($type === 'DESCRIPTIVE') {
            $base['modelAnswer'] = $row['modelanswer'] ?? $row['model'] ?? null;
        }
        return $base;
    }

    /**
     * Bulk import with full duplicate protection (in-file + against the bank).
     * @param array<int,array<string,mixed>> $rows
     */
    public static function import(array $rows, int $userId, array $defaults = []): array
    {
        $created = 0; $duplicates = 0; $invalid = 0;
        $rejected = [];
        $seen = [];

        foreach ($rows as $i => $raw) {
            $rowNum = $i + 2; // header is row 1
            $r = [];
            foreach ($raw as $k => $v) {
                $r[self::normKey((string) $k)] = $v === null ? '' : trim((string) $v);
            }

            $folder = trim($r['folder'] ?? $r['category'] ?? $defaults['folder'] ?? '');
            $subject = trim($r['subject'] ?? $defaults['subject'] ?? '');
            $topic = trim($r['topic'] ?? $defaults['topic'] ?? '');
            $text = $r['question'] ?? $r['text'] ?? $r['questiontext'] ?? '';

            if ($folder === '' || $subject === '' || $topic === '') {
                $invalid++; $rejected[] = ['row' => $rowNum, 'reason' => 'missing folder/subject/topic', 'kind' => 'invalid']; continue;
            }
            if ($text === '') {
                $invalid++; $rejected[] = ['row' => $rowNum, 'reason' => 'missing question text', 'kind' => 'invalid']; continue;
            }

            $key = Normalizer::questionText($text);
            if (isset($seen[$key])) {
                $duplicates++; $rejected[] = ['row' => $rowNum, 'reason' => 'duplicate within this file', 'kind' => 'duplicate']; continue;
            }
            $seen[$key] = true;

            try {
                $topicId = QuestionBank::ensureTopicPath($folder, $subject, $topic, $userId);
                $input = self::rowToQuestion($raw, $topicId);
                $res = self::create($input, $userId);
                if ($res['ok']) {
                    $created++;
                } elseif (isset($res['duplicate'])) {
                    $duplicates++;
                    $rejected[] = ['row' => $rowNum, 'reason' => "already in bank ({$res['duplicate']['subject']} → {$res['duplicate']['topic']})", 'kind' => 'duplicate'];
                } else {
                    $invalid++;
                    $rejected[] = ['row' => $rowNum, 'reason' => $res['error'], 'kind' => 'invalid'];
                }
            } catch (\Throwable $e) {
                $invalid++;
                $rejected[] = ['row' => $rowNum, 'reason' => $e->getMessage() ?: 'invalid row', 'kind' => 'invalid'];
            }
        }

        Audit::log('question.bulkImport', ['detail' => compact('created', 'duplicates', 'invalid')]);
        return ['ok' => true, 'total' => count($rows), 'created' => $created, 'duplicates' => $duplicates, 'invalid' => $invalid, 'rejected' => $rejected];
    }
}
