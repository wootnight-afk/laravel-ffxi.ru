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
                {{ __('site.comments.verify_email') }}
            @elseif ($user->isBanned())
                {{ __('site.comments.banned') }}
            @else
                {{ __('site.comments.no_permission') }}
            @endif
        </div>
    @else
        <div
            style="margin-top: 24px;"
            x-data="commentForm()"
        >
            <form x-on:submit.prevent="submit" x-ref="form">
                <div class="form-group" style="max-width: 100%;">
                    <label for="comment-body">{{ __('site.comments.your_comment') }}</label>
                    <textarea
                        id="comment-body"
                        class="form-control"
                        x-model="body"
                        x-ref="body"
                        rows="4"
                        maxlength="2000"
                        minlength="20"
                        required
                        placeholder="{{ __('site.comments.placeholder') }}"
                        style="resize: vertical;"
                    ></textarea>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        <span x-text="body.length"></span> / 2000
                    </div>
                </div>

                <div x-show="error" style="color: #dc2626; font-size: 13px; margin-bottom: 8px;" x-text="error"></div>
                <div x-show="success" style="color: #15803d; font-size: 13px; margin-bottom: 8px;" x-text="success"></div>

                <button type="submit" class="btn btn-primary" x-bind:disabled="loading">
                    <span x-show="! loading">{{ __('site.comments.send') }}</span>
                    <span x-show="loading">{{ __('site.comments.sending') }}</span>
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
                                this.error = {{ Illuminate\Support\Js::from(__('site.comments.min_length')) }};
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
                                    this.error = data.message || {{ Illuminate\Support\Js::from(__('site.comments.send_failed')) }};
                                    return;
                                }

                                this.success = data.message;
                                this.body = '';

                                setTimeout(() => window.location.reload(), 1200);
                            } catch (e) {
                                this.error = {{ Illuminate\Support\Js::from(__('site.comments.connection_error')) }};
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
        {!! __('site.comments.login_required', [
            'login' => '<a href="'.route('login').'">'.e(__('site.common.login')).'</a>',
        ]) !!}
    </div>
@endauth
