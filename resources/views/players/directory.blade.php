@extends('layouts.app')

@section('title', __('site.players.directory.title'))

@section('content')
    <x-players-layout :title="__('site.players.directory.title')">
        <form method="GET" action="{{ route('players.directory') }}" class="players-search">
            <div class="form-group players-search__field">
                <input
                    type="text"
                    name="q"
                    class="form-control"
                    value="{{ $search }}"
                    placeholder="{{ __('site.players.directory.search_placeholder') }}"
                    maxlength="24"
                    aria-label="{{ __('site.players.directory.search_placeholder') }}"
                >
                <button type="submit" class="btn btn-primary">{{ __('site.players.directory.search_submit') }}</button>
            </div>
        </form>

        @if ($users->isEmpty())
            <div class="content-text">
                <p>{{ __('site.players.directory.empty') }}</p>
            </div>
        @else
            <div class="players-cards">
                @foreach ($users as $user)
                    <div class="players-card">
                        <div class="players-card__head">
                            <x-avatar :user="$user" :size="64" />
                            <div class="players-card__identity">
                                <div class="players-card__name">
                                    @if ($user->isProfilePublic() || $user->id === auth()->id() || auth()->user()->isAdmin())
                                        <a href="{{ route('players.show', $user->name) }}">{{ $user->name }}</a>
                                    @else
                                        {{ $user->name }}
                                    @endif
                                </div>
                                <div class="players-card__meta">
                                    {{ $user->race ?? '—' }}
                                    @if ($user->main_job)
                                        · {{ $user->main_job }}
                                    @endif
                                </div>
                                @if ($user->rank)
                                    <div class="players-card__rank">
                                        <x-user-rank :user="$user" :compact="true" />
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="players-card__stats">
                            <span>{{ __('site.players.directory.news_count') }}: <strong>{{ $user->news_count }}</strong></span>
                            <span>{{ __('site.players.directory.comments_count') }}: <strong>{{ $user->comments_count }}</strong></span>
                            <span>{{ __('site.players.directory.registered') }}: <strong><x-naive-date :value="$user->created_at" /></strong></span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="players-pagination">
                {{ $users->links() }}
            </div>
        @endif
    </x-players-layout>
@endsection
