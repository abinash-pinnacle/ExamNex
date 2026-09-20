<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Question;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function storeFolder(Request $request)
    {
        $name = trim($request->input('name', ''));
        if ($name === '') {
            return back()->withErrors(['name' => 'Name is required']);
        }
        try {
            $f = Folder::create(['name' => $name, 'created_by' => $request->user()->id]);
            Audit::log('folder.create', ['entity' => 'Folder', 'entity_id' => $f->id]);
        } catch (QueryException) {
            return back()->withErrors(['name' => 'A folder with that name already exists.']);
        }
        return back()->with('status', 'Folder created.');
    }

    public function storeSubject(Request $request)
    {
        $request->validate(['folder_id' => 'required|exists:folders,id', 'name' => 'required|string|max:80']);
        try {
            $s = Subject::create(['folder_id' => $request->folder_id, 'name' => trim($request->name)]);
            Audit::log('subject.create', ['entity' => 'Subject', 'entity_id' => $s->id]);
        } catch (QueryException) {
            return back()->withErrors(['name' => 'A subject with that name already exists in this folder.']);
        }
        return back()->with('status', 'Subject created.');
    }

    public function storeTopic(Request $request)
    {
        $request->validate(['subject_id' => 'required|exists:subjects,id', 'name' => 'required|string|max:80']);
        try {
            $t = Topic::create(['subject_id' => $request->subject_id, 'name' => trim($request->name)]);
            Audit::log('topic.create', ['entity' => 'Topic', 'entity_id' => $t->id]);
        } catch (QueryException) {
            return back()->withErrors(['name' => 'A topic with that name already exists in this subject.']);
        }
        return back()->with('status', 'Topic created.');
    }

    public function renameFolder(Request $request, Folder $folder)
    {
        $name = trim($request->input('name', ''));
        if ($name === '') {
            return back()->withErrors(['name' => 'Name is required']);
        }
        try {
            $folder->update(['name' => $name]);
            // keep denormalised question strings in sync
            \App\Models\Question::whereHas('topicRef.subject', fn ($q) => $q->where('folder_id', $folder->id))
                ->update(['category' => $name]);
        } catch (QueryException) {
            return back()->withErrors(['name' => 'A folder with that name already exists.']);
        }
        return back()->with('status', 'Folder renamed.');
    }

    public function renameSubject(Request $request, Subject $subject)
    {
        $name = trim($request->input('name', ''));
        if ($name === '') {
            return back()->withErrors(['name' => 'Name is required']);
        }
        try {
            $subject->update(['name' => $name]);
            \App\Models\Question::whereHas('topicRef', fn ($q) => $q->where('subject_id', $subject->id))
                ->update(['subject' => $name]);
        } catch (QueryException) {
            return back()->withErrors(['name' => 'A subject with that name already exists in this folder.']);
        }
        return back()->with('status', 'Subject renamed.');
    }

    public function renameTopic(Request $request, Topic $topic)
    {
        $name = trim($request->input('name', ''));
        if ($name === '') {
            return back()->withErrors(['name' => 'Name is required']);
        }
        try {
            $topic->update(['name' => $name]);
            \App\Models\Question::where('topic_id', $topic->id)->update(['topic' => $name]);
        } catch (QueryException) {
            return back()->withErrors(['name' => 'A topic with that name already exists in this subject.']);
        }
        return back()->with('status', 'Topic renamed.');
    }

    public function destroyFolder(Folder $folder)
    {
        $count = Question::whereHas('topicRef.subject', fn ($q) => $q->where('folder_id', $folder->id))->count();
        if ($count > 0) {
            return back()->withErrors(['folder' => "This folder has {$count} question(s). Move or delete them first."]);
        }
        $folder->delete();
        Audit::log('folder.delete', ['entity' => 'Folder', 'entity_id' => $folder->id]);
        return back()->with('status', 'Folder deleted.');
    }

    public function destroySubject(Subject $subject)
    {
        $count = Question::whereHas('topicRef', fn ($q) => $q->where('subject_id', $subject->id))->count();
        if ($count > 0) {
            return back()->withErrors(['subject' => "This subject has {$count} question(s). Move or delete them first."]);
        }
        $subject->delete();
        Audit::log('subject.delete', ['entity' => 'Subject', 'entity_id' => $subject->id]);
        return back()->with('status', 'Subject deleted.');
    }

    public function destroyTopic(Topic $topic)
    {
        $count = Question::where('topic_id', $topic->id)->count();
        if ($count > 0) {
            return back()->withErrors(['topic' => "This topic has {$count} question(s). Move or delete them first."]);
        }
        $topic->delete();
        Audit::log('topic.delete', ['entity' => 'Topic', 'entity_id' => $topic->id]);
        return back()->with('status', 'Topic deleted.');
    }
}
