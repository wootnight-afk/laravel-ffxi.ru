@extends('errors.layout')

@php
    $retryAfter = 60;

    if (isset($exception) && method_exists($exception, 'getHeaders')) {
        $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? $retryAfter);
    }

    $retryAfter = max(1, min($retryAfter, 3600));
@endphp

@section('code', '429')
@section('title', __('site.errors.429.title'))
@section('message', __('site.errors.429.message'))

@section('actions')
    <button
        type="button"
        class="btn btn-primary"
        data-retry-button
        data-retry-seconds="{{ $retryAfter }}"
        disabled
    >{{ __('site.errors.retry_in', ['seconds' => $retryAfter]) }}</button>
    <a href="{{ route('home') }}" class="btn">{{ __('site.errors.home') }}</a>

    <script>
        (function () {
            var button = document.querySelector('[data-retry-button]');
            var seconds = button ? parseInt(button.getAttribute('data-retry-seconds'), 10) : 0;
            var template = @json(__('site.errors.retry_in', ['seconds' => '__SECONDS__']));

            if (!button || seconds <= 0) {
                return;
            }

            var tick = function () {
                seconds -= 1;

                if (seconds <= 0) {
                    button.disabled = false;
                    button.textContent = @json(__('site.errors.retry'));
                    button.addEventListener('click', function () {
                        window.location.reload();
                    });

                    return;
                }

                button.textContent = template.replace('__SECONDS__', String(seconds));
                window.setTimeout(tick, 1000);
            };

            window.setTimeout(tick, 1000);
        })();
    </script>
@endsection
