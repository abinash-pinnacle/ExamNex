<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // ================= STAFF (Users page) =================

    public function index(Request $request)
    {
        $users = User::whereIn('role', ['ADMIN', 'TEST_CREATOR']);
        if ($s = trim($request->get('search', ''))) {
            $users->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        $users = $users->latest()->paginate(20)->withQueryString();
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can create staff accounts.');
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in(['ADMIN', 'TEST_CREATOR'])],
            'password' => 'required|string|min:6',
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => $data['password'],
            'is_active' => true,
        ]);
        Audit::log('user.create', ['entity' => 'User', 'entity_id' => $user->id, 'detail' => ['role' => $user->role]]);
        return back()->with('status', 'Staff account created.');
    }

    // ================= CANDIDATES (students) =================

    public function candidates(Request $request)
    {
        $q = User::where('role', 'CANDIDATE')->withCount(['assignments', 'attempts']);
        if ($s = trim($request->get('search', ''))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('student_id', 'like', "%{$s}%"));
        }
        if ($b = trim($request->get('batch', ''))) {
            $q->where('batch', $b);
        }
        $candidates = $q->latest()->paginate(20)->withQueryString();
        $batches = User::where('role', 'CANDIDATE')->whereNotNull('batch')->distinct()->pluck('batch');
        return view('users.candidates', compact('candidates', 'batches'));
    }

    public function storeCandidate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => 'required|string|min:6',
            'student_id' => 'nullable|string|max:60|unique:users,student_id',
            'batch' => 'nullable|string|max:120',
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'CANDIDATE',
            'password' => $data['password'],
            'student_id' => $data['student_id'] ?? null,
            'batch' => $data['batch'] ?? null,
            'is_active' => true,
        ]);
        Audit::log('candidate.create', ['entity' => 'User', 'entity_id' => $user->id]);
        return back()->with('status', 'Candidate added.');
    }

    // ================= shared actions =================

    public function toggle(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot disable your own account.']);
        }
        $user->update(['is_active' => ! $user->is_active]);
        Audit::log('user.toggle', ['entity' => 'User', 'entity_id' => $user->id, 'detail' => ['active' => $user->is_active]]);
        return back()->with('status', 'Updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        if (in_array($user->role, ['ADMIN', 'TEST_CREATOR'], true) && ! $request->user()->isAdmin()) {
            abort(403, 'Only an admin can delete staff accounts.');
        }
        $attempts = $user->attempts()->count();
        if ($attempts > 0) {
            return back()->withErrors(['user' => "{$user->name} has {$attempts} attempt(s)/result(s). Use \"Disable\" to keep the records, or remove those results first."]);
        }
        $name = $user->name;
        $user->delete();
        Audit::log('user.delete', ['entity' => 'User', 'entity_id' => $user->id, 'detail' => ['name' => $name]]);
        return back()->with('status', "Deleted {$name}.");
    }
}
