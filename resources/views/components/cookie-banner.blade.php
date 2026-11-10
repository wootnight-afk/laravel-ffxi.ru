@php
    $hideBanner = request()->cookie('cookie_banner_ack') === '1';
@endphp

@if (! $hideBanner)
    <div class="cookie-banner" data-cookie-banner>
        <div>
            {!! __('site.cookie_banner.message', [
                'cookie' => '<a href="'.route('cookie').'">'.e(__('site.cookie_banner.cookie_link')).'</a>',
                'privacy' => '<a href="'.route('privacy').'">'.e(__('site.cookie_banner.privacy_link')).'</a>',
            ]) !!}
        </div>
        <button type="button" class="btn btn-primary" data-cookie-banner-dismiss>{{ __('site.cookie_banner.dismiss') }}</button>
    </div>
@endif
