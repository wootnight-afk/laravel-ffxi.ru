@extends('layouts.app')

@section('title', __('site.cabinet.characters.title'))

@section('content')
    <h1 class="page-title">{{ __('site.cabinet.characters.title') }}</h1>

    <div class="content-text">
        <a href="{{ route('cabinet.show') }}" class="btn">{{ __('site.cabinet.characters.back_to_cabinet') }}</a>
    </div>

    @if ($servers->isEmpty())
        <div class="content-text">
            <p>{{ __('site.cabinet.characters.no_servers') }}</p>
        </div>
    @else
        @if ($characters->isEmpty())
            <div class="content-text">
                <p>{{ __('site.cabinet.characters.empty') }}</p>
            </div>
        @else
            <div class="players-cards">
                @foreach ($characters as $character)
                    <div class="players-card">
                        <div class="players-card__head">
                            <div class="players-card__identity">
                                <div class="players-card__name">{{ $character->name }}</div>
                                <div class="players-card__meta">{{ $character->server?->name ?? '—' }}</div>
                                <div class="players-card__meta">
                                    {{ $character->race?->label() ?? __('site.cabinet.characters.race_none') }}
                                    @if ($character->main_job)
                                        · {{ $character->main_job }}
                                        @if ($character->mainJobLevel() !== null)
                                            {{ $character->mainJobLevel() }}
                                        @endif
                                    @endif
                                </div>
                                <div class="players-card__meta">
                                    {{ $character->is_public ? __('site.cabinet.characters.visibility_public') : __('site.cabinet.characters.visibility_hidden') }}
                                </div>
                            </div>
                        </div>

                        @if ($activeCharacterId === $character->getKey())
                            <p class="players-card__meta">
                                <strong>{{ __('site.cabinet.characters.active_badge') }}</strong>
                            </p>
                        @else
                            <form method="POST" action="{{ route('players.active-character.store') }}">
                                @csrf
                                <input type="hidden" name="character_id" value="{{ $character->getKey() }}">
                                <button type="submit" class="btn">
                                    {{ __('site.cabinet.characters.make_active') }}
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('cabinet.characters.show', $character) }}" class="btn">
                            {{ __('site.cabinet.characters.open') }}
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        @include('cabinet.characters._legacy', ['legacy' => $legacy, 'characters' => $characters])

        <h2 class="section-title">{{ __('site.cabinet.characters.add_title') }}</h2>

        <form method="POST" action="{{ route('cabinet.characters.store') }}" class="players-form">
            @csrf

            <div class="form-group">
                <label for="new-character-name">{{ __('site.cabinet.characters.fields.name') }}</label>
                <input
                    id="new-character-name"
                    type="text"
                    name="name"
                    class="form-control"
                    value="{{ old('name') }}"
                    maxlength="24"
                    required
                >
                @error('name')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="new-character-server">{{ __('site.cabinet.characters.fields.server') }}</label>
                <select id="new-character-server" name="game_server_id" class="form-control" required>
                    @foreach ($servers as $server)
                        <option value="{{ $server->id }}" @selected((int) old('game_server_id') === $server->id)>{{ $server->name }}</option>
                    @endforeach
                </select>
                @error('game_server_id')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_public" value="1" @checked(old('is_public', true))>
                    {{ __('site.cabinet.characters.fields.is_public') }}
                </label>
            </div>

            <button type="submit" class="btn btn-primary">{{ __('site.cabinet.characters.add_submit') }}</button>
        </form>
    @endif
@endsection
