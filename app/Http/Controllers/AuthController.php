<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('budget.index');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $lang = $request->cookie('lang', 'sr');
            throw ValidationException::withMessages([
                'email' => $lang === 'en' ? 'Incorrect email or password.' : 'Pogrešan email ili lozinka.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('budget.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'string', 'email']]);
        $lang = $request->cookie('lang', 'sr');

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', $this->passwordStatusMessage($status, $lang));
        }

        return back()->withErrors(['email' => $this->passwordStatusMessage($status, $lang)]);
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $lang = $request->cookie('lang', 'sr');

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', $this->passwordStatusMessage($status, $lang));
        }

        return back()->withErrors(['email' => $this->passwordStatusMessage($status, $lang)]);
    }

    private function passwordStatusMessage(string $status, string $lang): string
    {
        $messages = [
            Password::RESET_LINK_SENT => [
                'sr' => 'Poslali smo ti link za resetovanje lozinke na email.',
                'en' => "We've emailed you a password reset link.",
            ],
            Password::PASSWORD_RESET => [
                'sr' => 'Lozinka je uspešno promenjena. Prijavi se.',
                'en' => 'Your password has been reset. Please log in.',
            ],
            Password::INVALID_USER => [
                'sr' => 'Ne postoji nalog sa tim email-om.',
                'en' => "We can't find a user with that email address.",
            ],
            Password::INVALID_TOKEN => [
                'sr' => 'Link za resetovanje je nevažeći ili je istekao.',
                'en' => 'This password reset link is invalid or has expired.',
            ],
            Password::RESET_THROTTLED => [
                'sr' => 'Sačekaj malo pre nego što probaš ponovo.',
                'en' => 'Please wait before retrying.',
            ],
        ];

        return $messages[$status][$lang] ?? $status;
    }
}
