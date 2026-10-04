@php
    $started = $event->exists && $event->starts_at->lessThanOrEqualTo(now());
    $startsAt = $event->starts_at?->copy()->setTimezone($timezone)->format('Y-m-d\TH:i');
    $registrationClose = $event->registration_close?->copy()->setTimezone($timezone)->format('Y-m-d\TH:i');
@endphp

<form method="POST" action="{{ $action }}" style="max-width: 760px;">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if (! $started)
        <div class="form-group">
            <label for="event-type">Тип</label>
            <select id="event-type" class="form-control" name="type_id" required>
                <option value="">Выберите тип</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((string) old('type_id', $event->type_id) === (string) $type->id)>
                        {{ $type->title }}
                    </option>
                @endforeach
            </select>
            @error('type_id')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="event-title">Название</label>
            <input id="event-title" class="form-control" name="title" maxlength="150" required value="{{ old('title', $event->title) }}">
            @error('title')<div class="form-error">{{ $message }}</div>@enderror
        </div>
    @endif

    <div class="form-group">
        <label for="event-description">Описание (Markdown)</label>
        <textarea id="event-description" class="form-control" name="description" rows="7" maxlength="10000" required>{{ old('description', $event->description) }}</textarea>
        @error('description')<div class="form-error">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label for="event-location">Место</label>
        <input id="event-location" class="form-control" name="location" maxlength="120" value="{{ old('location', $event->location) }}">
        @error('location')<div class="form-error">{{ $message }}</div>@enderror
    </div>

    @if (! $started)
        <div class="form-group">
            <label for="event-starts-at">Дата и время ({{ $timezone }})</label>
            <input id="event-starts-at" class="form-control" type="datetime-local" name="starts_at" required value="{{ old('starts_at', $startsAt) }}">
            @error('starts_at')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="event-duration">Длительность, минут</label>
            <input id="event-duration" class="form-control" type="number" name="duration_minutes" min="1" max="65535" value="{{ old('duration_minutes', $event->duration_minutes) }}">
            @error('duration_minutes')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="event-capacity">Максимум участников (минимум 2; пусто — без лимита)</label>
            <input id="event-capacity" class="form-control" type="number" name="max_participants" min="2" max="100" value="{{ old('max_participants', $event->max_participants) }}">
            @error('max_participants')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="event-registration-close">Регистрация до (необязательно, {{ $timezone }})</label>
            <input id="event-registration-close" class="form-control" type="datetime-local" name="registration_close" value="{{ old('registration_close', $registrationClose) }}">
            @error('registration_close')<div class="form-error">{{ $message }}</div>@enderror
        </div>
    @endif

    <button class="btn btn-primary" type="submit">Сохранить</button>
    <a class="btn" href="{{ $event->exists ? route('events.show', $event) : route('events.index') }}">Отмена</a>
</form>
