@php
    /** @var \App\Models\User $user */
@endphp

@if (session('status'))
    <div class="form-status" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

<h2 class="section-title" style="margin-top: 0;">Опасная зона</h2>

@if ($user->isDeletionRequested())
    <div style="padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-light);">
        <p style="margin: 0; font-size: 14px;">
            Ваш аккаунт ожидает удаления. Свяжитесь с администрацией для отмены или уточнения статуса.
        </p>
    </div>
@else
    <div style="padding: 20px; border: 2px solid #dc2626; border-radius: 8px; background: rgba(220, 38, 38, 0.05);">
        <h3 style="font-size: 16px; color: #dc2626; margin-top: 0; margin-bottom: 12px;">
            Удаление аккаунта
        </h3>

        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
            Это <strong>запрос на удаление</strong>, а не мгновенное действие. Что произойдёт:
        </p>

        <ul style="font-size: 13px; line-height: 1.7; margin: 0 0 16px 20px; padding: 0;">
            <li>Ваш аккаунт будет закрыт — вход в систему станет невозможен.</li>
            <li>Публичный профиль и страница игрока станут недоступны.</li>
            <li>Вы будете разлогинены со всех устройств.</li>
            <li>Ваши существующие материалы остаются и будут отображаться как «[аккаунт удалён]».</li>
            <li>Окончательное удаление данных выполняет администрация.</li>
            <li>Восстановление возможно только через администрацию.</li>
        </ul>

        <form method="POST" action="{{ route('cabinet.danger.request') }}">
            @csrf

            <div class="form-group">
                <label for="danger_reason">Причина (обязательно)</label>
                <textarea
                    id="danger_reason"
                    name="reason"
                    class="form-control"
                    rows="3"
                    minlength="3"
                    maxlength="500"
                    required
                    placeholder="Коротко объясните причину…"
                    style="resize: vertical;"
                >{{ old('reason') }}</textarea>
                @error('reason') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="danger_password">Текущий пароль</label>
                <input
                    type="password"
                    id="danger_password"
                    name="password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                >
                @error('password') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" style="padding: 12px; background: var(--bg-card); border-radius: 4px;">
                <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.5;">
                    <input
                        type="checkbox"
                        name="confirm"
                        value="1"
                        required
                        style="margin-top: 3px;"
                        {{ old('confirm') ? 'checked' : '' }}
                    >
                    <span>
                        Я понимаю последствия запроса на удаление аккаунта.
                    </span>
                </label>
                @error('confirm') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <button
                type="submit"
                class="btn"
                style="background: #dc2626; color: #fff; border-color: #dc2626;"
                onclick="return confirm('Вы уверены, что хотите отправить запрос на удаление аккаунта?');"
            >
                Запросить удаление аккаунта
            </button>
        </form>
    </div>
@endif
