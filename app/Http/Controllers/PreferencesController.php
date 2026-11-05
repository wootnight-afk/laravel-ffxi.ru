<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Requests\UpdateLocalePreferenceRequest;
use App\Http\Requests\UpdateTimezonePreferenceRequest;
use App\Models\User;
use App\Services\LocalePreference;
use Illuminate\Http\RedirectResponse;
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
                ->withErrors(['locale' => 'Не удалось сохранить язык интерфейса. Попробуйте ещё раз.'])
                ->withInput();
        }

        $message = $locale === null
            ? 'Выбран автоматический язык интерфейса. Настройка сохранена; применение будет включено на следующем этапе.'
            : 'Язык интерфейса сохранён. Переключение языка будет включено на следующем этапе.';

        return $this->profileRedirect()->with('status', $message);
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
                ->withErrors(['timezone' => 'Не удалось сохранить часовой пояс. Попробуйте ещё раз.'])
                ->withInput();
        }

        $message = $user->timezone === null
            ? 'Выбран автоматический часовой пояс. Настройка сохранена; применение будет включено на следующем этапе.'
            : 'Часовой пояс сохранён. Применение пояса к датам будет включено на следующем этапе.';

        return $this->profileRedirect()->with('status', $message);
    }

    private function profileRedirect(): RedirectResponse
    {
        return redirect()->route('cabinet.tab', ['tab' => 'profile']);
    }
}
