<form method="POST" action="{{ $action }}" class="players-form">
    @csrf
    @method($method)

    <div class="form-group">
        <label for="character-name">{{ __('site.cabinet.characters.fields.name') }}</label>
        <input
            id="character-name"
            type="text"
            name="name"
            class="form-control"
            value="{{ old('name', $character->name) }}"
            maxlength="24"
            required
        >
        @error('name')
            <div class="form-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="character-server">{{ __('site.cabinet.characters.fields.server') }}</label>
        <select id="character-server" name="game_server_id" class="form-control" required>
            @foreach ($servers as $server)
                <option value="{{ $server->id }}" @selected((int) old('game_server_id', $character->game_server_id) === $server->id)>
                    {{ $server->name }}@unless ($server->is_active) — {{ __('site.cabinet.characters.server_inactive') }}@endunless
                </option>
            @endforeach
        </select>
        @error('game_server_id')
            <div class="form-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="character-race">{{ __('site.cabinet.characters.fields.race') }}</label>
        <select id="character-race" name="race" class="form-control">
            <option value="">{{ __('site.cabinet.characters.race_none') }}</option>
            @foreach (\App\Enums\CharacterRace::cases() as $race)
                <option value="{{ $race->value }}" @selected(old('race', $character->race?->value) === $race->value)>
                    {{ $race->label() }}
                </option>
            @endforeach
        </select>
        @error('race')
            <div class="form-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="character-main-job">{{ __('site.cabinet.characters.fields.main_job') }}</label>
        <select id="character-main-job" name="main_job" class="form-control">
            <option value="">{{ __('site.cabinet.characters.main_job_none') }}</option>
            @foreach (\App\Enums\GameJob::cases() as $job)
                <option value="{{ $job->value }}" @selected(old('main_job', $character->main_job) === $job->value)>
                    {{ $job->label() }}
                </option>
            @endforeach
        </select>
        @error('main_job')
            <div class="form-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label>
            {{-- A hidden 0 keeps the field present in every submit, so an
                 unchecked box clears publication instead of being skipped. --}}
            <input type="hidden" name="is_public" value="0">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $character->is_public))>
            {{ __('site.cabinet.characters.fields.is_public') }}
        </label>
    </div>

    @include('cabinet.characters._jobs', ['levels' => $levels])

    @php
        // One editor per language: the field follows the interface language and
        // the version of the other language stays untouched in the database
        // (STAGE-1 §18.1).
        $legendLocale = app()->getLocale() === 'en' ? 'en' : 'ru';
        $legendValue = $legendLocale === 'en' ? $character->legend_en : $character->legend_ru;
    @endphp

    <div class="form-group">
        <label for="character-legend">{{ __('site.cabinet.characters.fields.legend_'.$legendLocale) }}</label>
        <textarea
            id="character-legend"
            name="legend_{{ $legendLocale }}"
            class="form-control"
            rows="8"
            maxlength="4000"
        >{{ old('legend_'.$legendLocale, $legendValue) }}</textarea>
        <p class="character-jobs__hint">{{ __('site.cabinet.characters.legend.hint') }}</p>
        @error('legend_'.$legendLocale)
            <div class="form-error">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary">{{ $submit }}</button>
</form>
