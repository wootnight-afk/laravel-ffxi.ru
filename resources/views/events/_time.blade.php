@php
    /** @var \App\Models\Event $event */
    $value = $value ?? $event->starts_at;
    $viewerTimezone = app(\App\Services\TimezoneResolver::class)->resolve(auth()->user());
    $local = \App\Services\TimezoneOptions::isValid($event->timezone)
        ? \App\Support\DateFormat::eventLocal($value, $viewerTimezone, app()->getLocale())
        : null;
@endphp

<x-utc-datetime :value="$value" />@if ($local !== null) · {{ $local }} @endif
