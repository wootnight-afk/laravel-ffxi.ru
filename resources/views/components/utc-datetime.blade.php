@props(['value' => null, 'format' => 'datetime'])

@php
    $moment = \App\Support\DateFormat::toCarbon($value);
    $text = \App\Support\DateFormat::utc($moment, app()->getLocale(), $format);
    $iso = \App\Support\DateFormat::iso($moment, 'UTC');
@endphp

@if ($moment === null)
    <span {{ $attributes }}>{{ \App\Support\DateFormat::PLACEHOLDER }}</span>
@else
    <time {{ $attributes->merge(['datetime' => $iso]) }}>{{ $text }}</time>
@endif
