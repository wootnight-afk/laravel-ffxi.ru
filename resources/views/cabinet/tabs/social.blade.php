@php
    /** @var \App\Models\User $user */
    $platforms = config('social.platforms', []);
    $links = $user->socialLinks()->get();
@endphp

@if (session('status'))
    <div class="form-status" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

<h2 class="section-title" style="margin-top: 0;">Социальные сети</h2>

@if ($links->isEmpty())
    <p style="color: var(--text-muted); font-size: 14px;">У вас пока нет добавленных ссылок.</p>
@else
    <div style="margin-bottom: 24px;">
        @foreach ($links as $link)
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 8px; background: var(--bg-card);">
                <div style="min-width: 0; flex: 1;">
                    <div style="font-size: 14px; font-weight: 600;">
                        {{ $platforms[$link->type]['icon'] ?? '🔗' }}
                        {{ $platforms[$link->type]['title'] ?? $link->type }}
                        @if ($link->label)
                            — {{ $link->label }}
                        @endif
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); word-break: break-all;">
                        {{ $link->url }}
                    </div>
                </div>

                <div style="display: flex; gap: 6px; flex-shrink: 0;">
                    <form method="POST" action="{{ route('cabinet.social.toggle', $link) }}">
                        @csrf
                        <button type="submit" class="btn" style="padding: 4px 10px; font-size: 12px;">
                            {{ $link->is_visible ? 'Скрыть' : 'Показать' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('cabinet.social.destroy', $link) }}" onsubmit="return confirm('Удалить ссылку?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn" style="padding: 4px 10px; font-size: 12px; color: #dc2626;">
                            Удалить
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div style="padding: 16px; border: 1px dashed var(--border-color); border-radius: 8px;">
    <h3 style="font-size: 14px; margin-bottom: 12px;">Добавить ссылку</h3>

    <form method="POST" action="{{ route('cabinet.social.store') }}">
        @csrf

        <div class="form-group">
            <label for="type">Платформа</label>
            <select id="type" name="type" class="form-control" required>
                @foreach ($platforms as $key => $platform)
                    <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>
                        {{ $platform['icon'] ?? '' }} {{ $platform['title'] }}
                    </option>
                @endforeach
            </select>
            @error('type') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="username">Username (если платформа поддерживает)</label>
            <input
                type="text"
                id="username"
                name="username"
                class="form-control"
                value="{{ old('username') }}"
                maxlength="100"
            >
            @error('username') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="url">Полный URL (если у платформы нет шаблона)</label>
            <input
                type="url"
                id="url"
                name="url"
                class="form-control"
                value="{{ old('url') }}"
                maxlength="500"
                placeholder="https://…"
            >
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                Только https://. Проверяется сервером.
            </div>
            @error('url') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="label">Подпись (необязательно)</label>
            <input
                type="text"
                id="label"
                name="label"
                class="form-control"
                value="{{ old('label') }}"
                maxlength="50"
            >
        </div>

        <button type="submit" class="btn btn-primary">Добавить</button>
    </form>
</div>
