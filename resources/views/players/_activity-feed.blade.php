<section style="margin-top: 28px;">
    <h2 class="section-title">Активность</h2>
    @if ($activityGroups === [])
        <p class="news-meta">Пока активности нет.</p>
    @else
        @foreach ($activityGroups as $group)
            @include('activity._item', ['group' => $group, 'viewer' => $viewer])
        @endforeach
    @endif
    <p style="margin-top: 12px;"><a href="{{ route('activity.index') }}">Вся активность →</a></p>
</section>
