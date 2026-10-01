@props(['news'])

@auth
    @php
        $user = auth()->user();
        $canPost = $user->hasVerifiedEmail()
            && ! $user->isBanned()
            && $user->can('comments.create');
    @endphp

    @if (! $canPost)
        <div style="margin-top: 16px; padding: 12px; background: #fff3cd; border-radius: 4px; font-size: 13px; color: #856404;">
            @if (! $user->hasVerifiedEmail())
                Подтвердите email, чтобы оставлять комментарии.
            @elseif ($user->isBanned())
                Ваш аккаунт заблокирован.
            @else
                У вас нет права оставлять комментарии.
            @endif
        </div>
    @else
        <div
            style="margin-top: 24px;"
            x-data="commentForm()"
        >
            <form x-on:submit.prevent="submit" x-ref="form">
                <div class="form-group" style="max-width: 100%;">
                    <label for="comment-body">Ваш комментарий</label>
                    <textarea
                        id="comment-body"
                        class="form-control"
                        x-model="body"
                        x-ref="body"
                        rows="4"
                        maxlength="2000"
                        minlength="20"
                        required
                        placeholder="Минимум 20 символов…"
                        style="resize: vertical;"
                    ></textarea>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        <span x-text="body.length"></span> / 2000
                    </div>
                </div>

                <div x-show="error" style="color: #dc2626; font-size: 13px; margin-bottom: 8px;" x-text="error"></div>
                <div x-show="success" style="color: #15803d; font-size: 13px; margin-bottom: 8px;" x-text="success"></div>

                <button type="submit" class="btn btn-primary" x-bind:disabled="loading">
                    <span x-show="! loading">Отправить</span>
                    <span x-show="loading">Отправка…</span>
                </button>
            </form>
        </div>

        @push('scripts')
            <script>
                function commentForm() {
                    return {
                        body: '',
                        loading: false,
                        error: null,
                        success: null,
                        async submit() {
                            if (this.body.length < 20) {
                                this.error = 'Минимум 20 символов.';
                                return;
                            }

                            this.loading = true;
                            this.error = null;
                            this.success = null;

                            try {
                                const response = await fetch('{{ route('comments.store', $news->slug) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    },
                                    body: JSON.stringify({ body: this.body }),
                                });

                                const data = await response.json().catch(() => ({}));

                                if (! response.ok) {
                                    this.error = data.message || 'Не удалось отправить комментарий.';
                                    return;
                                }

                                this.success = data.message;
                                this.body = '';

                                setTimeout(() => window.location.reload(), 1200);
                            } catch (e) {
                                this.error = 'Ошибка соединения.';
                            } finally {
                                this.loading = false;
                            }
                        },
                    };
                }
            </script>
        @endpush
    @endif
@else
    <div style="margin-top: 16px; padding: 12px; background: var(--bg-light); border-radius: 4px; font-size: 13px; color: var(--text-muted);">
        <a href="{{ route('login') }}">Войдите</a>, чтобы оставить комментарий.
    </div>
@endauth
