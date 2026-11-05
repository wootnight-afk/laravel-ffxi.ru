@php
    $races = [
        '' => '— не указана —',
        'hume' => 'Hume',
        'elvaan' => 'Elvaan',
        'tarutaru' => 'Tarutaru',
        'mithra' => 'Mithra',
        'galka' => 'Galka',
    ];
@endphp

@if (session('status'))
    <div class="form-status" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

<div style="display: flex; gap: 24px; margin-bottom: 32px; flex-wrap: wrap;">
    <div style="text-align: center;">
        <x-avatar :user="$user" :size="128" />
        <div style="margin-top: 12px; display: flex; flex-direction: column; gap: 6px;">
            <form
                method="POST"
                action="{{ route('cabinet.avatar.upload') }}"
                enctype="multipart/form-data"
            >
                @csrf
                <input
                    type="file"
                    name="avatar"
                    accept="image/jpeg,image/png,image/webp"
                    required
                    style="font-size: 12px;"
                >
                <button type="submit" class="btn" style="margin-top: 6px; font-size: 13px;">Загрузить</button>
            </form>

            @if ($user->avatar_path)
                <form method="POST" action="{{ route('cabinet.avatar.delete') }}">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="btn"
                        style="font-size: 13px;"
                        onclick="return confirm('Удалить аватар?');"
                    >
                        Удалить
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div style="flex: 1; min-width: 240px;">
        <h2 class="section-title" style="margin-top: 0;">Профиль</h2>

        <form method="POST" action="{{ route('cabinet.profile.update') }}">
            @csrf

            <div class="form-group">
                <label for="race">Раса</label>
                <select id="race" name="race" class="form-control">
                    @foreach ($races as $value => $label)
                        <option value="{{ $value }}" {{ old('race', $user->race) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('race') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="main_job">Main job</label>
                <input
                    type="text"
                    id="main_job"
                    name="main_job"
                    class="form-control"
                    value="{{ old('main_job', $user->main_job) }}"
                    maxlength="10"
                    placeholder="PLD, RDM, WHM…"
                >
                @error('main_job') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" style="max-width: 100%;">
                <label for="legend">Легенда (Markdown)</label>
                <textarea
                    id="legend"
                    name="legend"
                    class="form-control"
                    rows="8"
                    maxlength="4000"
                    placeholder="Расскажите о своём персонаже…"
                    style="resize: vertical;"
                >{{ old('legend', $user->legend) }}</textarea>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                    Поддерживается Markdown: **жирный**, *курсив*, списки, ссылки.
                </div>
                @error('legend') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="phone">Телефон (необязательно)</label>
                <input
                    type="text"
                    id="phone"
                    name="phone"
                    class="form-control"
                    value="{{ old('phone', $user->phone) }}"
                    maxlength="32"
                >
                <label style="display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 13px;">
                    <input type="checkbox" name="phone_is_public" value="1" {{ old('phone_is_public', $user->phone_is_public) ? 'checked' : '' }}>
                    Показывать телефон в профиле
                </label>
                @error('phone') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" style="padding: 12px; background: var(--bg-light); border-radius: 4px;">
                <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.5;">
                    <input type="checkbox" name="is_profile_public" value="1" {{ old('is_profile_public', $user->is_profile_public) ? 'checked' : '' }} style="margin-top: 3px;">
                    <span>
                        <strong>Открытый профиль</strong><br>
                        Другие игроки увидят вашу легенду, новости, фото и соцсети.
                    </span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>

        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border-color);">
            <h2 class="section-title">Язык и часовой пояс</h2>
            <p style="color: var(--text-muted); font-size: 13px; line-height: 1.6;">
                Здесь сохраняются ваши предпочтения. Фактическое переключение языка интерфейса
                и применение часового пояса к датам будут включены на следующих этапах.
            </p>

            @php
                $localeOld = old('locale', $user->locale ?? '');
                $localeValue = is_string($localeOld) ? $localeOld : '';
                $localeIsKnown = $localeValue === '' || in_array($localeValue, \App\Enums\Locale::values(), true);

                $timezoneOld = old('timezone', $user->timezone ?? '');
                $timezoneValue = is_string($timezoneOld) ? $timezoneOld : '';
                $timezoneIsKnown = $timezoneValue === '' || \App\Services\TimezoneOptions::isValid($timezoneValue);
            @endphp

            <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start;">
                <form method="POST" action="{{ route('cabinet.preferences.locale') }}" style="flex: 1; min-width: 240px;">
                    @csrf

                    <div class="form-group">
                        <label for="locale">Язык интерфейса</label>
                        <select id="locale" name="locale" class="form-control">
                            <option value="" @selected($localeValue === '')>Авто — по языку браузера</option>
                            @if (! $localeIsKnown)
                                <option value="{{ $localeValue }}" selected>{{ $localeValue }} (недопустимый код)</option>
                            @endif
                            @foreach (\App\Enums\Locale::cases() as $locale)
                                <option value="{{ $locale->value }}" @selected($localeValue === $locale->value)>
                                    {{ $locale->label() }}
                                </option>
                            @endforeach
                        </select>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Пустое значение — «авто»: язык будет определяться по браузеру.
                        </div>
                        @error('locale') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn">Сохранить язык</button>
                </form>

                <form method="POST" action="{{ route('cabinet.preferences.timezone') }}" style="flex: 1; min-width: 280px;">
                    @csrf

                    <div class="form-group">
                        <label for="timezone">Часовой пояс</label>
                        <select id="timezone" name="timezone" class="form-control">
                            <option value="" @selected($timezoneValue === '')>Авто — по часовому поясу браузера</option>
                            @if (! $timezoneIsKnown)
                                <option value="{{ $timezoneValue }}" selected>{{ $timezoneValue }} (недопустимый IANA)</option>
                            @endif
                            @foreach ($timezoneOptions as $region => $zones)
                                <optgroup label="{{ $region }}">
                                    @foreach ($zones as $value => $label)
                                        <option value="{{ $value }}" @selected($timezoneValue === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Смещение в подписи указано на текущий момент и учитывает переход на летнее время.
                        </div>
                        @error('timezone') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn">Сохранить часовой пояс</button>
                </form>
            </div>
        </div>
    </div>
</div>
