<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\GradingController;
use App\Http\Controllers\PublicTestController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---- Public entry ----
Route::get('/', fn () => auth()->check()
    ? redirect(AuthController::home(auth()->user()))
    : redirect()->route('login'));

// ---- One-time deployment setup (browser, no SSH needed). Remove SETUP_KEY after use. ----
Route::get('/deploy-setup/{key}', function (string $key) {
    $expected = env('SETUP_KEY');
    abort_unless($expected && hash_equals((string) $expected, $key), 404);
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $migrate = \Illuminate\Support\Facades\Artisan::output();
    // Seed only if there are no users yet (fresh install).
    $seeded = 'skipped (data already exists)';
    if (\App\Models\User::count() === 0) {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $seeded = 'done';
    }
    \Illuminate\Support\Facades\Artisan::call('optimize');
    return response('<pre style="font:14px monospace;padding:20px">'
        . "ExamNex setup complete.\n\nMigrations:\n" . e($migrate)
        . "\nSeed: {$seeded}\n\nLogin: admin@examnex.test / password"
        . "\n\n⚠ SECURITY: remove the SETUP_KEY line from your .env now, then reload.\n</pre>");
});

// ---- Auth ----
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---- Shared public test link (self-registration) ----
Route::get('/test/{code}', [PublicTestController::class, 'show'])->name('public.test');
Route::post('/test/{code}', [PublicTestController::class, 'register'])->middleware('throttle:20,1');

// ---- Staff area (ADMIN / TEST_CREATOR) ----
Route::middleware(['auth', 'role:ADMIN,TEST_CREATOR'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Question bank hierarchy
    Route::post('/folders', [FolderController::class, 'storeFolder'])->name('folders.store');
    Route::patch('/folders/{folder}', [FolderController::class, 'renameFolder'])->name('folders.rename');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroyFolder'])->name('folders.destroy');
    Route::post('/subjects', [FolderController::class, 'storeSubject'])->name('subjects.store');
    Route::patch('/subjects/{subject}', [FolderController::class, 'renameSubject'])->name('subjects.rename');
    Route::delete('/subjects/{subject}', [FolderController::class, 'destroySubject'])->name('subjects.destroy');
    Route::post('/topics', [FolderController::class, 'storeTopic'])->name('topics.store');
    Route::patch('/topics/{topic}', [FolderController::class, 'renameTopic'])->name('topics.rename');
    Route::delete('/topics/{topic}', [FolderController::class, 'destroyTopic'])->name('topics.destroy');

    // Questions
    Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::get('/questions/create', [QuestionController::class, 'create'])->name('questions.create');
    Route::post('/questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::post('/questions/check-duplicate', [QuestionController::class, 'checkDuplicate'])->name('questions.checkDuplicate');
    Route::get('/questions/import', [QuestionController::class, 'importForm'])->name('questions.importForm');
    Route::get('/questions/import/template', [QuestionController::class, 'template'])->name('questions.template');
    Route::post('/questions/import', [QuestionController::class, 'import'])->name('questions.import');
    Route::get('/questions/ai', [AiController::class, 'form'])->name('questions.ai');
    Route::post('/questions/ai/generate', [AiController::class, 'generate'])->name('questions.ai.generate');
    Route::post('/questions/ai/import', [AiController::class, 'import'])->name('questions.ai.import');
    Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
    Route::put('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

    // Tests
    Route::get('/tests', [TestController::class, 'index'])->name('tests.index');
    Route::get('/tests/create', [TestController::class, 'create'])->name('tests.create');
    Route::post('/tests', [TestController::class, 'store'])->name('tests.store');
    Route::post('/tests/template', [TestController::class, 'storeTemplate'])->name('tests.template');
    Route::get('/tests/{test}', [TestController::class, 'show'])->name('tests.show');
    Route::get('/tests/{test}/edit', [TestController::class, 'edit'])->name('tests.edit');
    Route::put('/tests/{test}', [TestController::class, 'update'])->name('tests.update');
    Route::post('/tests/{test}/questions', [TestController::class, 'addQuestions'])->name('tests.addQuestions');
    Route::delete('/tests/{test}/questions/{question}', [TestController::class, 'removeQuestion'])->name('tests.removeQuestion');
    Route::post('/tests/{test}/assign', [TestController::class, 'assign'])->name('tests.assign');
    Route::post('/tests/{test}/publish', [TestController::class, 'publish'])->name('tests.publish');
    Route::post('/tests/{test}/archive', [TestController::class, 'archive'])->name('tests.archive');
    Route::get('/tests/{test}/monitor', [TestController::class, 'monitor'])->name('tests.monitor');

    // Grading
    Route::get('/grading', [GradingController::class, 'index'])->name('grading.index');
    Route::post('/grading/{answer}', [GradingController::class, 'grade'])->name('grading.grade');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{test}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{test}/export', [ReportController::class, 'export'])->name('reports.export');

    // Candidates (students)
    Route::get('/candidates', [UserController::class, 'candidates'])->name('candidates.index');
    Route::post('/candidates', [UserController::class, 'storeCandidate'])->name('candidates.store');

    // Users (staff)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/account', [SettingsController::class, 'account'])->name('settings.account');
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('/settings/organization', [SettingsController::class, 'organization'])->name('settings.organization');
        Route::post('/settings/exam-defaults', [SettingsController::class, 'examDefaults'])->name('settings.examDefaults');
    });
});

// ---- Candidate area ----
Route::middleware(['auth', 'role:CANDIDATE'])->group(function () {
    Route::get('/candidate', [CandidateController::class, 'home'])->name('candidate.home');
    Route::get('/candidate/test/{test}', [CandidateController::class, 'intro'])->name('candidate.intro');

    Route::post('/candidate/test/{test}/start', [AttemptController::class, 'start'])->name('attempt.start');
    Route::get('/candidate/attempt/{attempt}', [AttemptController::class, 'run'])->name('attempt.run');
    Route::post('/candidate/attempt/{attempt}/save', [AttemptController::class, 'save'])->name('attempt.save');
    Route::post('/candidate/attempt/{attempt}/event', [AttemptController::class, 'event'])->name('attempt.event');
    Route::post('/candidate/attempt/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempt.submit');
    Route::get('/candidate/attempt/{attempt}/result', [AttemptController::class, 'result'])->name('attempt.result');
    Route::get('/candidate/attempt/{attempt}/certificate', [AttemptController::class, 'certificate'])->name('attempt.certificate');
});
