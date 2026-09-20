<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index');
    }

    public function organization(Request $request)
    {
        $data = $request->validate([
            'org_name' => 'required|string|max:60',
            'tagline' => 'nullable|string|max:80',
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'conducted_by' => 'nullable|string|max:80',
            'conducted_by_sub' => 'nullable|string|max:80',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);
        Setting::putMany(collect($data)->except('logo')->all());

        if ($request->hasFile('logo')) {
            $dir = public_path('uploads');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $name = 'logo-' . time() . '.' . ($request->file('logo')->extension() ?: 'png');
            $request->file('logo')->move($dir, $name);
            Setting::put('org_logo', '/uploads/' . $name);
        } elseif ($request->boolean('remove_logo')) {
            Setting::put('org_logo', '');
        }

        Audit::log('settings.organization');
        return back()->with('status', 'Organization settings saved.');
    }

    public function examDefaults(Request $request)
    {
        $data = $request->validate([
            'default_duration' => 'required|integer|min:1|max:1440',
            'default_passing' => 'required|integer|min:0|max:100000',
            'default_max_attempts' => 'required|integer|min:1|max:20',
            'default_result_visibility' => ['required', Rule::in(['IMMEDIATE', 'AFTER_REVIEW', 'HIDDEN'])],
        ]);
        Setting::putMany(array_map('strval', $data));
        Audit::log('settings.examDefaults');
        return back()->with('status', 'Exam defaults saved.');
    }

    public function account(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'nullable|required_with:password|current_password',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = $data['password']; // hashed via cast
        }
        $user->save();
        Audit::log('settings.account', ['entity' => 'User', 'entity_id' => $user->id]);
        return back()->with('status', 'Your account was updated.');
    }
}
