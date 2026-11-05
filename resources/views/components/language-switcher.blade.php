@php
    $currentLocale = app()->getLocale();
@endphp

{{-- Plain POST form: switching works without JavaScript (ADR-011 §2.2). --}}
<form method="POST" action="{{ route('locale.update') }}" class="locale-switcher" aria-label="Язык интерфейса">
    @csrf
    @foreach (\App\Enums\Locale::cases() as $locale)
        <button
            type="submit"
            name="locale"
            value="{{ $locale->value }}"
            class="locale-switcher__option {{ $currentLocale === $locale->value ? 'is-active' : '' }}"
            lang="{{ $locale->value }}"
            @if ($currentLocale === $locale->value) aria-current="true" @endif
        >{{ mb_strtoupper($locale->value) }}</button>
    @endforeach
</form>
