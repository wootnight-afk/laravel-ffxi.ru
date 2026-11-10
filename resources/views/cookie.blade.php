@extends('layouts.app')

@section('title', __('site.cookie.title'))

@section('content')
    <h1 class="page-title">{{ __('site.cookie.heading') }}</h1>

    <div class="content-text">
        <p>{!! __('site.cookie.intro') !!}</p>

        <h2 class="section-title">{{ __('site.cookie.table_caption') }}</h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                    <th style="padding: 8px;">{{ __('site.cookie.columns.name') }}</th>
                    <th style="padding: 8px;">{{ __('site.cookie.columns.purpose') }}</th>
                    <th style="padding: 8px;">{{ __('site.cookie.columns.lifetime') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (__('site.cookie.items') as $item)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px;"><code>{{ $item['name'] }}</code></td>
                        <td style="padding: 8px;">{{ $item['purpose'] }}</td>
                        <td style="padding: 8px;">{{ $item['lifetime'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2 class="section-title">{{ __('site.cookie.storage_title') }}</h2>
        <p>{!! __('site.cookie.storage_text') !!}</p>

        <p>{!! __('site.cookie.privacy_link', [
            'link' => '<a href="'.route('privacy').'">'.e(__('site.cookie.privacy_link_text')).'</a>',
        ]) !!}</p>
    </div>
@endsection
