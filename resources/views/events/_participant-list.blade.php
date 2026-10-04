@if ($isGuest)
    <p class="news-meta">{{ $total }} участников</p>
@elseif ($participants->isEmpty())
    <p class="news-meta">Участников пока нет.</p>
@else
    <ul style="padding-left: 20px;">
        @foreach ($participants as $participant)
            <li>
                {{ $participant->user?->name ?? '[аккаунт удалён]' }}
                @if ($participant->jobs)
                    <span class="news-meta">· {{ $participant->jobs }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
