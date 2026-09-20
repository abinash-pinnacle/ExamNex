<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared public test link (self-registration). One link identifies the TEST;
 * each student self-registers and gets their OWN candidate identity + attempt,
 * reusing the normal exam engine unchanged. Ported from publicTest.ts.
 */
class PublicTestController extends Controller
{
    private function resolveTest(string $code): ?Test
    {
        return Test::where('access_code', $code)->where('public_access', true)->first();
    }

    public function show(string $code)
    {
        $test = $this->resolveTest($code);
        if (! $test || $test->status !== 'PUBLISHED') {
            return view('public.unavailable', ['message' => 'This test is currently unavailable.']);
        }
        $now = now();
        if ($test->starts_at && $now->lt($test->starts_at)) {
            return view('public.unavailable', ['message' => 'This test opens at ' . $test->starts_at->format('d M Y, H:i') . '.']);
        }
        if ($test->ends_at && $now->gt($test->ends_at)) {
            return view('public.unavailable', ['message' => 'This test window has closed.']);
        }

        $registered = DB::table('test_assignments')->where('test_id', $test->id)->count();
        if ($registered >= $test->max_candidates) {
            return view('public.unavailable', ['message' => 'This test has reached its maximum number of candidates.']);
        }

        return view('public.register', compact('test', 'code'));
    }

    public function register(Request $request, string $code)
    {
        $test = $this->resolveTest($code);
        if (! $test || $test->status !== 'PUBLISHED') {
            return back()->withErrors(['register' => 'This test is currently unavailable.']);
        }
        $now = now();
        if (($test->starts_at && $now->lt($test->starts_at)) || ($test->ends_at && $now->gt($test->ends_at))) {
            return back()->withErrors(['register' => 'This test is not open right now.']);
        }

        $d = $request->validate([
            'full_name' => 'required|string|min:2|max:120',
            'student_id' => 'required|string|min:1|max:60',
            'contact' => 'required|string|min:3|max:120',
            'department' => 'nullable|string|max:120',
        ]);

        // Password-protected test.
        if ($test->access_password && $request->input('access_password') !== $test->access_password) {
            return back()->withInput()->withErrors(['register' => 'Incorrect test password.']);
        }

        // Find-or-create candidate. SECURITY: only ever reuse an account that was
        // itself created by THIS public flow (role CANDIDATE + pc.*@public.exam).
        // Never authenticate a pre-provisioned candidate or staff account that
        // merely happens to share this (guessable) student_id — that would be
        // account takeover.
        $user = User::where('student_id', $d['student_id'])
            ->where('role', 'CANDIDATE')
            ->where('email', 'like', 'pc.%@public.exam')
            ->first();
        if (! $user) {
            // If the roll number already belongs to some other (non-public) account,
            // keep this self-registrant on a distinct student_id so we never collide
            // with or log into that account.
            $sid = $d['student_id'];
            if (User::where('student_id', $sid)->exists()) {
                $sid .= '-' . Str::lower(Str::random(6));
            }
            $slug = Str::slug($sid) ?: Str::random(8);
            $user = User::create([
                'email' => "pc.{$slug}@public.exam",
                'name' => $d['full_name'],
                'role' => 'CANDIDATE',
                'password' => Str::uuid()->toString(), // login-less; hashed by cast
                'student_id' => $sid,
                'contact' => $d['contact'],
                'batch' => $d['department'] ?? null,
            ]);
        }

        // Capacity + assignment.
        $already = $test->assignments()->where('user_id', $user->id)->exists();
        if (! $already) {
            $registered = DB::table('test_assignments')->where('test_id', $test->id)->count();
            if ($registered >= $test->max_candidates) {
                return back()->withErrors(['register' => 'This test has reached its maximum number of candidates.']);
            }
            $test->assignments()->create(['user_id' => $user->id, 'assigned_at' => now()]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        Audit::log('publicTest.register', ['entity' => 'Test', 'entity_id' => $test->id, 'detail' => ['studentId' => $d['student_id']]]);

        return redirect()->route('candidate.intro', $test->id);
    }
}
