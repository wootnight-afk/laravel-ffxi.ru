@if ($isGuest)
    <p class="news-meta">{{ trans_choice('site.events.participants_count', $total) }}</p>
@elseif ($participants->isEmpty())
    <p class="news-meta">{{ __('site.events.participants_empty') }}</p>
@else
    <ul style="padding-left: 20px;">
        @foreach ($participants as $participant)
            <li>
                {{ $participant->user?->name ?? __('site.common.deleted_account') }}
                @if ($participant->jobs)
                    <span class="news-meta">· {{ $participant->jobs }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
