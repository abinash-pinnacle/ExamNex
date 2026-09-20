<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect($this->home(Auth::user()));
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withInput(['email' => $data['email']])->withErrors(['email' => 'Invalid email or password.']);
        }
        $user = Auth::user();
        if (! $user->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'This account is disabled.']);
        }

        $request->session()->regenerate();
        Audit::log('auth.login', ['entity' => 'User', 'entity_id' => $user->id]);
        return redirect()->intended($this->home($user));
    }

    public function logout(Request $request)
    {
        Audit::log('auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    /** Landing route per role. */
    public static function home($user): string
    {
        return $user->isCandidate() ? route('candidate.home') : route('dashboard');
    }
}
