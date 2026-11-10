<section id="event-comments" style="margin-top: 36px;">
    <h2 class="section-title">{{ __('site.events.comments_title') }}</h2>

    @if ($comments->isEmpty())
        <p class="news-meta">{{ __('site.events.comments_empty') }}</p>
    @endif

    @foreach ($comments as $comment)
        <article id="comment-{{ $comment->id }}" style="padding: 16px 0; border-bottom: 1px solid var(--border-color);">
            <p class="news-meta">
                <strong>{{ $comment->user?->name ?? __('site.common.deleted_account') }}</strong>
                · <x-datetime :value="$comment->created_at" />
                @if ($comment->edited_at)
                    · {{ __('site.comments.edited') }}
                @endif
                @if ($comment->status === \App\Models\Comment::STATUS_PENDING)
                    · {{ __('site.comments.pending') }}
                @elseif ($comment->status === \App\Models\Comment::STATUS_REJECTED)
                    · {{ __('site.comments.rejected') }}
                @elseif ($comment->status === \App\Models\Comment::STATUS_SPAM)
                    · {{ __('site.comments.spam') }}
                @endif
            </p>
            <div style="white-space: pre-wrap;">{{ $comment->body }}</div>

            @foreach ($comment->replies as $reply)
                <div style="margin: 12px 0 0 24px; padding-left: 12px; border-left: 2px solid var(--border-color);">
                    <p class="news-meta">
                        <strong>{{ $reply->user?->name ?? __('site.common.deleted_account') }}</strong>
                        · <x-datetime :value="$reply->created_at" />
                        @if ($reply->status === \App\Models\Comment::STATUS_PENDING)
                            · {{ __('site.comments.pending') }}
                        @elseif ($reply->status === \App\Models\Comment::STATUS_REJECTED)
                            · {{ __('site.comments.rejected') }}
                        @elseif ($reply->status === \App\Models\Comment::STATUS_SPAM)
                            · {{ __('site.comments.spam') }}
                        @endif
                    </p>
                    <div style="white-space: pre-wrap;">{{ $reply->body }}</div>
                </div>
            @endforeach

            @auth
                @can('create', \App\Models\Comment::class)
                    <details style="margin-top: 12px;">
                        <summary>{{ __('site.comments.reply') }}</summary>
                        <form method="POST" action="{{ route('events.comments.store', $event) }}" style="margin-top: 8px;">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                            <div class="form-group">
                                <label for="reply-{{ $comment->id }}">{{ __('site.comments.reply_label') }}</label>
                                <textarea id="reply-{{ $comment->id }}" class="form-control" name="body" rows="3" minlength="20" maxlength="2000" required></textarea>
                            </div>
                            <button class="btn" type="submit">{{ __('site.comments.reply_submit') }}</button>
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
                    <label for="event-comment-body">{{ __('site.comments.your_comment') }}</label>
                    <textarea id="event-comment-body" class="form-control" name="body" rows="4" minlength="20" maxlength="2000" required>{{ old('body') }}</textarea>
                    @error('body')<div role="alert" class="form-error">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary" type="submit">{{ __('site.comments.submit') }}</button>
            </form>
        @else
            <p class="news-meta" style="margin-top: 16px;">{{ __('site.comments.no_permission') }}</p>
        @endcan
    @else
        <p style="margin-top: 16px;">{!! __('site.comments.login_required', ['login' => '<a href="'.route('login').'">'.e(__('site.events.login_link')).'</a>']) !!}</p>
    @endauth
</section>
