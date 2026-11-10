@props(['comments', 'news'])

@php
    $user = auth()->user();
@endphp

<div class="comment-thread">
    @if ($comments->isEmpty())
        <p style="color: var(--text-muted); font-size: 14px;">
            {{ __('site.comments.empty') }}
        </p>
    @else
        @foreach ($comments as $comment)
            <article
                id="comment-{{ $comment->id }}"
                class="comment-item"
                style="padding: 16px 0; border-bottom: 1px solid var(--border-color);"
            >
                <header style="display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 6px;">
                    <div style="font-size: 13px;">
                        <strong style="color: var(--primary-color);">
                            {{ $comment->user?->name ?? __('site.common.deleted_account') }}
                        </strong>
                        <span style="color: var(--text-muted);">
                            · <x-datetime :value="$comment->created_at" />
                            @if ($comment->edited_at)
                                · <em>{{ __('site.comments.edited') }}</em>
                            @endif
                        </span>
                        @if ($comment->status === \App\Models\Comment::STATUS_PENDING)
                            <span style="color: #b45309; font-size: 12px; margin-left: 6px;">
                                {{ __('site.comments.pending') }}
                            </span>
                        @endif
                    </div>

                    @auth
                        @if ($user->id === $comment->user_id || $user->can('comments.moderate'))
                            <form
                                method="POST"
                                action="{{ route('comments.destroy', $comment) }}"
                                data-confirm="{{ __('site.comments.delete_confirm') }}"
                                x-data="{ confirmSubmit(event) { if (! confirm(this.$el.dataset.confirm)) event.preventDefault(); } }"
                                x-on:submit="confirmSubmit"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 12px; text-decoration: underline;"
                                >
                                    {{ __('site.comments.delete') }}
                                </button>
                            </form>
                        @else
                            <form
                                method="POST"
                                action="{{ route('comments.report', $comment) }}"
                                data-reported-label="{{ __('site.comments.reported') }}"
                                x-data="{ submitted: false }"
                                x-on:submit.prevent="
                                    if (submitted) return;
                                    submitted = true;
                                    fetch($el.action, {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            'Accept': 'application/json',
                                        },
                                    }).then(() => { $el.querySelector('button').textContent = $el.dataset.reportedLabel; });
                                "
                            >
                                @csrf
                                <button
                                    type="submit"
                                    style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 12px; text-decoration: underline;"
                                >
                                    {{ __('site.comments.report') }}
                                </button>
                            </form>
                        @endif
                    @endauth
                </header>

                <div style="font-size: 14px; line-height: 1.6; white-space: pre-wrap;">{{ $comment->body }}</div>

                @if ($comment->replies->isNotEmpty())
                    <div style="margin-top: 12px; margin-left: 32px; padding-left: 12px; border-left: 2px solid var(--border-color);">
                        @foreach ($comment->replies as $reply)
                            <div style="padding: 8px 0;">
                                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">
                                    <strong>{{ $reply->user?->name ?? __('site.common.deleted_account') }}</strong>
                                    · <x-datetime :value="$reply->created_at" />
                                </div>
                                <div style="font-size: 14px; line-height: 1.6; white-space: pre-wrap;">{{ $reply->body }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    @endif
</div>
