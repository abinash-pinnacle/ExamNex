<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Subject;
use App\Models\Topic;

class QuestionBank
{
    /** Create (or reuse) a Folder -> Subject -> Topic path and return the topic id. */
    public static function ensureTopicPath(string $folder, string $subject, string $topic, ?int $createdBy = null): int
    {
        $f = Folder::firstOrCreate(['name' => $folder], ['created_by' => $createdBy]);
        $s = Subject::firstOrCreate(['folder_id' => $f->id, 'name' => $subject]);
        $t = Topic::firstOrCreate(['subject_id' => $s->id, 'name' => $topic]);
        return $t->id;
    }

    /** Resolve a topic id to its Folder / Subject / Topic names. */
    public static function getTopicPath(int $topicId): ?array
    {
        $t = Topic::with('subject.folder')->find($topicId);
        if (! $t) {
            return null;
        }
        return [
            'topicId' => $t->id,
            'topic' => $t->name,
            'subjectId' => $t->subject_id,
            'subject' => $t->subject->name,
            'folderId' => $t->subject->folder_id,
            'folder' => $t->subject->folder->name,
        ];
    }
}
