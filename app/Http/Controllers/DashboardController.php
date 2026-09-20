<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'questions' => Question::count(),
            'tests' => Test::count(),
            'published' => Test::where('status', 'PUBLISHED')->count(),
            'candidates' => User::where('role', 'CANDIDATE')->count(),
            'attempts' => Attempt::count(),
        ];

        $growth = [
            'questions' => $this->growth(Question::query(), 'created_at'),
            'tests' => $this->growth(Test::query(), 'created_at'),
            'published' => $this->growth(Test::where('status', 'PUBLISHED'), 'updated_at'),
            'candidates' => $this->growth(User::where('role', 'CANDIDATE'), 'created_at'),
            'attempts' => $this->growth(Attempt::query(), 'started_at'),
        ];

        // Overall performance
        $evaluated = Attempt::where('status', 'EVALUATED')->whereNotNull('total_score')->get();
        $avgPct = 0;
        if ($evaluated->count()) {
            $avgPct = (int) round($evaluated->avg(fn ($a) => ($a->max_score ?? 0) > 0 ? ($a->total_score / $a->max_score) * 100 : 0));
        }
        $performance = [
            'avgPct' => $avgPct,
            'attempts' => Attempt::count(),
            'passed' => $evaluated->where('passed', true)->count(),
            'pending' => Attempt::whereIn('status', ['SUBMITTED', 'AUTO_SUBMITTED'])->count(),
        ];

        $pendingGrading = AttemptAnswer::where('graded', false)
            ->whereHas('question', fn ($q) => $q->where('type', 'DESCRIPTIVE'))
            ->whereHas('attempt', fn ($a) => $a->whereIn('status', ['SUBMITTED', 'AUTO_SUBMITTED']))
            ->count();

        // Real 7-day daily series for the sparklines.
        $series = [
            'questions' => $this->series(Question::query(), 'created_at'),
            'tests' => $this->series(Test::query(), 'created_at'),
            'published' => $this->series(Test::where('status', 'PUBLISHED'), 'updated_at'),
            'candidates' => $this->series(User::where('role', 'CANDIDATE'), 'created_at'),
            'attempts' => $this->series(Attempt::query(), 'started_at'),
        ];

        $recentAttempts = Attempt::with(['test', 'candidate'])->latest('started_at')->limit(6)->get();
        $activity = AuditLog::latest('created_at')->limit(6)->get();

        return view('dashboard.index', compact('stats', 'growth', 'performance', 'pendingGrading', 'recentAttempts', 'activity', 'series'));
    }

    /** Daily counts for the last 7 days (oldest → today). */
    private function series(Builder $query, string $col): array
    {
        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->copy()->subDays($i);
            $out[] = (clone $query)->whereBetween($col, [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->count();
        }
        return $out;
    }

    /** Period-over-period growth % (last 7 days vs the 7 days before). */
    private function growth(Builder $query, string $col): int
    {
        $now = now();
        $cur = (clone $query)->where($col, '>=', $now->copy()->subDays(7))->count();
        $prev = (clone $query)->whereBetween($col, [$now->copy()->subDays(14), $now->copy()->subDays(7)])->count();
        if ($prev === 0) {
            return $cur > 0 ? 100 : 0;
        }
        return (int) round((($cur - $prev) / $prev) * 100);
    }
}
