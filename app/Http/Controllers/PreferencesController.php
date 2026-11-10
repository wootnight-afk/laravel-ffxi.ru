<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Requests\UpdateLocalePreferenceRequest;
use App\Http\Requests\UpdateTimezonePreferenceRequest;
use App\Models\User;
use App\Services\LocalePreference;
use App\Services\LocaleResolver;
use App\Support\LocaleCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class PreferencesController extends Controller
{
    public function __construct(private readonly LocalePreference $localePreference) {}

    public function updateLocale(UpdateLocalePreferenceRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $value = $request->validated('locale');
        $locale = is_string($value) ? Locale::from($value) : null;

        try {
            $this->localePreference->update($user, $locale);
        } catch (Throwable $exception) {
            report($exception);

            return $this->profileRedirect()
                ->withErrors(['locale' => __('site.cabinet.flash.locale_failed')])
                ->withInput();
        }

        if ($locale === null) {
            // The response expires this cookie; ignore its stale request value too.
            app(Request::class)->cookies->remove(LocaleResolver::COOKIE);
        }

        $resolvedLocale = app(LocaleResolver::class)->resolve($user);
        app()->setLocale($resolvedLocale);
        Carbon::setLocale($resolvedLocale);

        $message = $locale === null
            ? __('site.cabinet.flash.locale_auto_saved')
            : __('site.cabinet.flash.locale_saved');

        // Keep the plain `locale` cookie in sync with the stored preference;
        // "auto" removes it so a stale guest value cannot override
        // Accept-Language (ADR-011 §2.2).
        return $this->profileRedirect()
            ->with('status', $message)
            ->withCookie($locale === null
                ? LocaleCookie::forget()
                : LocaleCookie::make($locale, $request->isSecure()));
    }

    public function updateTimezone(UpdateTimezonePreferenceRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $value = $request->validated('timezone');
        $user->timezone = is_string($value) ? $value : null;

        try {
            $user->save();
        } catch (Throwable $exception) {
            report($exception);

            return $this->profileRedirect()
                ->withErrors(['timezone' => __('site.cabinet.flash.timezone_failed')])
                ->withInput();
        }

        $message = $user->timezone === null
            ? __('site.cabinet.flash.timezone_auto_saved')
            : __('site.cabinet.flash.timezone_saved');

        return $this->profileRedirect()->with('status', $message);
    }

    private function profileRedirect(): RedirectResponse
    {
        return redirect()->route('cabinet.tab', ['tab' => 'profile']);
    }
}
