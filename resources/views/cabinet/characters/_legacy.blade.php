@if ($legacy !== null && $characters->count() >= 2)
    <div class="content-text character-legacy">
        <h2 class="section-title">{{ __('site.cabinet.characters.legacy.title') }}</h2>
        <p>{{ __('site.cabinet.characters.legacy.hint') }}</p>

        <ul>
            @foreach ($legacy['values'] as $field => $value)
                <li>
                    <strong>{{ __('site.cabinet.characters.fields.'.$field) }}:</strong>
                    {{ \Illuminate\Support\Str::limit($value, 120) }}
                </li>
            @endforeach
        </ul>

        <form method="POST" action="{{ route('cabinet.characters.legacy.store') }}">
            @csrf

            <div class="form-group">
                <label for="legacy-character">{{ __('site.cabinet.characters.legacy.choose') }}</label>
                <select id="legacy-character" name="character_id" class="form-control" required>
                    @foreach ($characters as $character)
                        <option value="{{ $character->id }}">
                            {{ $character->name }} — {{ $character->server?->name ?? '—' }}
                        </option>
                    @endforeach
                </select>
                @error('character_id')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">
                {{ __('site.cabinet.characters.legacy.submit') }}
            </button>
        </form>
    </div>
@endif
