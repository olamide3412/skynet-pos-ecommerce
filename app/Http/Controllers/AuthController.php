<?php

namespace App\Http\Controllers;

use App\Enums\StatusEnums;
use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        // If already logged in as staff/admin, redirect to dashboard
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }
        return inertia('Auth/Login');
    }


    public function login(Request $request)
    {
        $attributes = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
            'cf_turnstile_response' => ['required', new Turnstile()],
        ]);

        $login = $attributes['login'];
        $key = 'login:' . Str::lower($login) . '|' . $request->ip();

        // Check if the user has too many failed attempts
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $time = $this->formatLockoutTime($seconds);

            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Please try again in {$time}.",
            ]);
        }

        // Try to authenticate using email
        $credentials = ['email' => $login, 'password' => $attributes['password']];

        if (Auth::guard('web')->attempt($credentials, $request->remember ?? false)) {
            $user = Auth::guard('web')->user();

            if ($user->status === StatusEnums::Disable->value) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages([
                    'login' => 'Your account is disabled.',
                ]);
            }

            log_new("Login successful admin: " . $user->email);
            RateLimiter::clear($key);
            $request->session()->regenerate();
            
            return redirect()->intended(route('admin.dashboard'));
        }

        // Failed
        RateLimiter::hit($key, 7200);
        log_new("Failed login attempt with: $login IP: " . $request->ip());

        throw ValidationException::withMessages([
            'login' => 'Invalid Credentials',
        ]);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    protected function formatLockoutTime($seconds)
    {
        if ($seconds >= 3600) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ($minutes > 0 ? " and {$minutes} minute" . ($minutes > 1 ? 's' : '') : '');
        } elseif ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ($remainingSeconds > 0 ? " and {$remainingSeconds} second" . ($remainingSeconds > 1 ? 's' : '') : '');
        } else {
            return $seconds . ' second' . ($seconds > 1 ? 's' : '');
        }
    }
}
