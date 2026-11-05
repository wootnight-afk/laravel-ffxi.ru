@props(['value' => null, 'format' => 'datetime', 'timezoneMark' => null])

@php
    $moment = \App\Support\DateFormat::toCarbon($value);
    $timezone = app(\App\Services\TimezoneResolver::class)->resolve(auth()->user());
    $showTimezone = $timezoneMark ?? auth()->guest();
    $text = \App\Support\DateFormat::moment(
        $moment,
        $timezone,
        app()->getLocale(),
        $format,
        (bool) $showTimezone,
    );
    $iso = \App\Support\DateFormat::iso($moment, $timezone);
@endphp

@if ($moment === null)
    <span {{ $attributes }}>{{ \App\Support\DateFormat::PLACEHOLDER }}</span>
@else
    <time {{ $attributes->merge(['datetime' => $iso]) }}>{{ $text }}</time>
@endif
