<?php

namespace App\Http\Controllers;

use App\Actions\Identity\RegisterStudent;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:80'], 'password' => ['required', 'string', 'max:255']]);
        $identifier = Phone::identifier($data['identifier']);
        $user = User::where(str_starts_with($identifier, 'STU-') ? 'student_code' : 'phone', $identifier)->first();
        $key = 'login:'.($user ? 'user:'.$user->id : hash('sha256', $identifier));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['identifier' => __('ui.login_throttled')]);
        }
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $user || ! $valid || $user->status !== 'active' || ($user->must_change_password && $user->temporary_password_expires_at?->isPast())) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['identifier' => __('ui.login_failed')]);
        }
        RateLimiter::clear($key);
        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->session_version);
        if ($user->must_change_password) {
            return redirect()->route('profile');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request, RegisterStudent $register)
    {
        $request->merge(['phone' => Phone::normalize((string) $request->input('phone')), 'whatsapp_phone' => Phone::normalize((string) ($request->boolean('whatsapp_same') ? $request->input('phone') : $request->input('whatsapp_phone')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'phone' => ['required', new PhoneNumber, 'unique:users,phone'],
            'whatsapp_phone' => ['required', new PhoneNumber(false)], 'whatsapp_declared' => ['accepted'],
            'password' => ['required', 'confirmed', Password::min(8), 'max:255'],
            'university' => ['nullable', 'string', 'max:150'], 'university_student_id' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $user = $register->handle($data);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['phone' => __('ui.phone_unavailable')]);
        }

        return redirect()->route('login')->with('registered_code', $user->student_code);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
