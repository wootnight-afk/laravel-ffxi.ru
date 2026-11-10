@php
    $currentLocale = app()->getLocale();
@endphp

{{-- Admin panel language switch (ADR-011 §2.24): a plain POST form, so it
     works without JavaScript and reuses the single LocaleController source
     of truth (users.locale is written for authenticated users). --}}
<form
    method="POST"
    action="{{ route('locale.update') }}"
    style="display: flex; align-items: center; gap: 4px;"
    aria-label="{{ __('site.locale.switch_label') }}"
>
    @csrf

    @foreach (\App\Enums\Locale::cases() as $locale)
        <x-filament::button
            type="submit"
            name="locale"
            value="{{ $locale->value }}"
            size="xs"
            :color="$currentLocale === $locale->value ? 'primary' : 'gray'"
            :outlined="$currentLocale !== $locale->value"
        >{{ mb_strtoupper($locale->value) }}</x-filament::button>
    @endforeach
</form>
