<section id="event-comments" style="margin-top: 36px;">
    <h2 class="section-title">Обсуждение</h2>

    @if ($comments->isEmpty())
        <p class="news-meta">Комментариев пока нет.</p>
    @endif

    @foreach ($comments as $comment)
        <article id="comment-{{ $comment->id }}" style="padding: 16px 0; border-bottom: 1px solid var(--border-color);">
            <p class="news-meta">
                <strong>{{ $comment->user?->name ?? '[аккаунт удалён]' }}</strong>
                · {{ $comment->created_at->format('d.m.Y H:i') }}
                @if ($comment->edited_at)
                    · изменён
                @endif
                @if ($comment->status === \App\Models\Comment::STATUS_PENDING)
                    · на модерации
                @elseif ($comment->status === \App\Models\Comment::STATUS_REJECTED)
                    · отклонён
                @elseif ($comment->status === \App\Models\Comment::STATUS_SPAM)
                    · спам
                @endif
            </p>
            <div style="white-space: pre-wrap;">{{ $comment->body }}</div>

            @foreach ($comment->replies as $reply)
                <div style="margin: 12px 0 0 24px; padding-left: 12px; border-left: 2px solid var(--border-color);">
                    <p class="news-meta">
                        <strong>{{ $reply->user?->name ?? '[аккаунт удалён]' }}</strong>
                        · {{ $reply->created_at->format('d.m.Y H:i') }}
                        @if ($reply->status === \App\Models\Comment::STATUS_PENDING)
                            · на модерации
                        @elseif ($reply->status === \App\Models\Comment::STATUS_REJECTED)
                            · отклонён
                        @elseif ($reply->status === \App\Models\Comment::STATUS_SPAM)
                            · спам
                        @endif
                    </p>
                    <div style="white-space: pre-wrap;">{{ $reply->body }}</div>
                </div>
            @endforeach

            @auth
                @can('create', \App\Models\Comment::class)
                    <details style="margin-top: 12px;">
                        <summary>Ответить</summary>
                        <form method="POST" action="{{ route('events.comments.store', $event) }}" style="margin-top: 8px;">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                            <div class="form-group">
                                <label for="reply-{{ $comment->id }}">Ваш ответ</label>
                                <textarea id="reply-{{ $comment->id }}" class="form-control" name="body" rows="3" minlength="20" maxlength="2000" required></textarea>
                            </div>
                            <button class="btn" type="submit">Отправить ответ</button>
                        </form>
                    </details>
                @endcan
            @endauth
        </article>
    @endforeach

    @if ($comments->hasPages())
        <div style="margin-top: 16px;">{{ $comments->links() }}</div>
    @endif

    @auth
        @can('create', \App\Models\Comment::class)
            <form method="POST" action="{{ route('events.comments.store', $event) }}" style="margin-top: 20px;">
                @csrf
                <div class="form-group">
                    <label for="event-comment-body">Ваш комментарий</label>
                    <textarea id="event-comment-body" class="form-control" name="body" rows="4" minlength="20" maxlength="2000" required>{{ old('body') }}</textarea>
                    @error('body')<div role="alert" class="form-error">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary" type="submit">Отправить комментарий</button>
            </form>
        @else
            <p class="news-meta" style="margin-top: 16px;">У вас нет права оставлять комментарии.</p>
        @endcan
    @else
        <p style="margin-top: 16px;"><a href="{{ route('login') }}">Войдите</a>, чтобы оставить комментарий.</p>
    @endauth
</section>
