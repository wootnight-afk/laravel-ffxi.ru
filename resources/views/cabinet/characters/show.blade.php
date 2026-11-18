@extends('layouts.app')

@section('title', $character->name)

@section('content')
    <div class="character-page__head">
        <h1 class="page-title">{{ $character->name }}</h1>
        <div class="character-page__meta">
            {{ $character->server?->name ?? '—' }}
            ·
            {{ $character->is_public ? __('site.cabinet.characters.visibility_public') : __('site.cabinet.characters.visibility_hidden') }}
        </div>
    </div>

    @if (session('status'))
        <div class="content-text">
            <p>{{ session('status') }}</p>
        </div>
    @endif

    @include('cabinet.characters._form', [
        'action' => route('cabinet.characters.update', $character),
        'method' => 'PUT',
        'character' => $character,
        'servers' => $servers,
        'levels' => $levels,
        'submit' => __('site.cabinet.characters.save'),
    ])

    @php($legendHtml = $character->legendHtml(app()->getLocale()))
    <h2 class="section-title">{{ __('site.cabinet.characters.legend.title') }}</h2>
    <div class="content-text">
        @if ($legendHtml)
            {!! $legendHtml !!}
        @else
            <p>{{ __('site.cabinet.characters.legend.empty') }}</p>
        @endif
    </div>

    <h2 class="section-title">{{ __('site.cabinet.characters.danger.title') }}</h2>
    <form
        method="POST"
        action="{{ route('cabinet.characters.destroy', $character) }}"
        onsubmit="return confirm(@js(__('site.cabinet.characters.delete_confirm')))"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="btn">{{ __('site.cabinet.characters.delete') }}</button>
    </form>

    <p class="content-text">
        <a href="{{ route('cabinet.characters.index') }}" class="btn">
            {{ __('site.cabinet.characters.back_to_list') }}
        </a>
    </p>
@endsection
