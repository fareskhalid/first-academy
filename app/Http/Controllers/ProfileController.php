<?php

namespace App\Http\Controllers;

use App\Rules\PhoneNumber;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile');
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge(['phone' => Phone::normalize((string) $request->phone), 'whatsapp_phone' => Phone::normalize((string) $request->whatsapp_phone)]);
        $rules = ['name' => ['required', 'string', 'max:150'], 'phone' => ['required', new PhoneNumber, Rule::unique('users')->ignore($user)], 'current_password' => ['required', 'current_password']];
        if (! $user->isInstructor()) {
            $rules += ['whatsapp_phone' => ['required', new PhoneNumber(false)], 'whatsapp_declared' => ['accepted'], 'university' => ['nullable', 'string', 'max:150'], 'university_student_id' => ['nullable', 'string', 'max:100']];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($user, $data, $request) {
            $locked = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            $locked->update(['name' => $data['name'], 'phone' => $data['phone'], 'session_version' => $locked->session_version + 1, 'remember_token' => Str::random(60)]);
            if (! $user->isInstructor()) {
                $locked->profile()->update(['whatsapp_phone' => $data['whatsapp_phone'], 'whatsapp_declared_at' => now(), 'university' => $data['university'] ?? null, 'university_student_id' => $data['university_student_id'] ?? null]);
            }
            Audit::record($locked, 'profile_updated', $locked);
            $request->session()->put('auth_version', $locked->session_version);
        });
        $request->user()->refresh();
        $request->session()->regenerate();

        return back()->with('status_key', 'profile_saved');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', Password::min(8), 'max:255', 'different:current_password']]);
        DB::transaction(function () use ($request, $data) {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            $user->update(['password' => $data['password'], 'session_version' => $user->session_version + 1, 'remember_token' => Str::random(60), 'must_change_password' => false, 'temporary_password_expires_at' => null]);
            Audit::record($user, 'password_changed', $user);
            $request->session()->put('auth_version', $user->session_version);
        });
        $request->user()->refresh();
        $request->session()->regenerate();

        return redirect()->route('profile')->with('status_key', 'password_saved');
    }

    public function locale(Request $request)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(['en', 'ar'])]]);
        $request->user()->update($data);

        return response()->json(['locale' => $data['locale']]);
    }
}
