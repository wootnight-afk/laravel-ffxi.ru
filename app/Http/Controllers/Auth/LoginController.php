<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Anti-enumeration: одинаковое сообщение для «не найден email» и «неверный пароль».
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        $user = Auth::user();

        if ($user->isBanned()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Аккаунт заблокирован до ' . $user->banned_until->format('d.m.Y H:i') . '.',
            ]);
        }

        if ($user->isDeletionRequested()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Аккаунт ожидает удаления. Обратитесь к администрации.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
