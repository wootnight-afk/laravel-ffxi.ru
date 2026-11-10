<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Models\User;
use App\Services\LocalePreference;
use App\Support\LocaleCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Explicit interface language switch (ADR-011 §2.2).
 *
 * Guest: session and cookie `locale` are updated.
 * Authenticated: users.locale is written first; the session is updated by
 * LocalePreference only after a successful write, and the cookie mirrors the
 * stored value. A failed write leaves session and cookie untouched.
 */
final class LocaleController extends Controller
{
    public function __invoke(Request $request, LocalePreference $preference): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locale::values())],
        ]);

        $value = $validated['locale'];
        $locale = Locale::from(is_string($value) ? $value : Locale::Ru->value);
        $user = $request->user();

        if ($user instanceof User) {
            try {
                $preference->update($user, $locale);
            } catch (Throwable $exception) {
                report($exception);

                return $this->back()
                    ->withErrors(['locale' => __('site.locale.switch_failed')])
                    ->withInput();
            }
        } else {
            $request->session()->put(LocalePreference::SESSION_KEY, $locale->value);
        }

        app()->setLocale($locale->value);
        Carbon::setLocale($locale->value);

        return $this->back()->withCookie(
            LocaleCookie::make($locale, $request->isSecure()),
        );
    }

    private function back(): RedirectResponse
    {
        return redirect()->back(fallback: route('home'));
    }
}
