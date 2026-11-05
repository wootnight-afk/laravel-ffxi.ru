@props(['value' => null])

@php
    $date = \App\Support\DateFormat::toCarbon($value);
@endphp

@if ($date === null)
    <span {{ $attributes }}>{{ \App\Support\DateFormat::PLACEHOLDER }}</span>
@else
    <time {{ $attributes->merge(['datetime' => $date->toDateString()]) }}>{{ \App\Support\DateFormat::date($date, app()->getLocale()) }}</time>
@endif
