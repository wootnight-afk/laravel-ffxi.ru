@php
    /** @var \App\Models\User $user */
@endphp

@if (session('status'))
    <div class="form-status" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

<h2 class="section-title" style="margin-top: 0;">{{ __('site.cabinet.danger.title') }}</h2>

@if ($user->isDeletionRequested())
    <div style="padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-light);">
        <p style="margin: 0; font-size: 14px;">
            {{ __('site.cabinet.danger.deletion_pending') }}
        </p>
    </div>
@else
    @if (! $user->isSuspended())
        <div style="padding: 20px; border: 2px solid #d97706; border-radius: 8px; background: rgba(217, 119, 6, 0.05); margin-bottom: 20px;">
            <h3 style="font-size: 16px; color: #b45309; margin-top: 0; margin-bottom: 12px;">
                {{ __('site.cabinet.danger.suspend_title') }}
            </h3>

            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
                {{ __('site.cabinet.danger.suspend_hint') }}
            </p>

            <form method="POST" action="{{ route('cabinet.danger.suspend') }}">
                @csrf

                <div class="form-group">
                    <label for="suspension_reason">{{ __('site.cabinet.danger.reason') }}</label>
                    <textarea
                        id="suspension_reason"
                        name="reason"
                        class="form-control"
                        rows="3"
                        minlength="3"
                        maxlength="500"
                        required
                        style="resize: vertical;"
                    >{{ old('reason') }}</textarea>
                    @error('reason') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="suspension_password">{{ __('site.cabinet.danger.current_password') }}</label>
                    <input
                        type="password"
                        id="suspension_password"
                        name="password"
                        class="form-control"
                        required
                        autocomplete="current-password"
                    >
                    @error('password') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group" style="padding: 12px; background: var(--bg-card); border-radius: 4px;">
                    <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.5;">
                        <input
                            type="checkbox"
                            name="confirm"
                            value="1"
                            required
                            style="margin-top: 3px;"
                            {{ old('confirm') ? 'checked' : '' }}
                        >
                        <span>{{ __('site.cabinet.danger.suspend_confirm') }}</span>
                    </label>
                    @error('confirm') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <button
                    type="submit"
                    class="btn"
                    style="background: #d97706; color: #fff; border-color: #d97706;"
                    onclick="return confirm('{{ __('site.cabinet.danger.suspend_confirm_dialog') }}');"
                >
                    {{ __('site.cabinet.danger.suspend_action') }}
                </button>
            </form>
        </div>
    @endif

    <div style="padding: 20px; border: 2px solid #dc2626; border-radius: 8px; background: rgba(220, 38, 38, 0.05);">
        <h3 style="font-size: 16px; color: #dc2626; margin-top: 0; margin-bottom: 12px;">
            {{ __('site.cabinet.danger.delete_title') }}
        </h3>

        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
            {!! __('site.cabinet.danger.delete_intro') !!}
        </p>

        <ul style="font-size: 13px; line-height: 1.7; margin: 0 0 16px 20px; padding: 0;">
            <li>{{ __('site.cabinet.danger.delete_item_1') }}</li>
            <li>{{ __('site.cabinet.danger.delete_item_2') }}</li>
            <li>{{ __('site.cabinet.danger.delete_item_3') }}</li>
            <li>{{ __('site.cabinet.danger.delete_item_4') }}</li>
            <li>{{ __('site.cabinet.danger.delete_item_5') }}</li>
            <li>{{ __('site.cabinet.danger.delete_item_6') }}</li>
        </ul>

        <form method="POST" action="{{ route('cabinet.danger.request') }}">
            @csrf

            <div class="form-group">
                <label for="danger_reason">{{ __('site.cabinet.danger.delete_reason') }}</label>
                <textarea
                    id="danger_reason"
                    name="reason"
                    class="form-control"
                    rows="3"
                    minlength="3"
                    maxlength="500"
                    required
                    placeholder="{{ __('site.cabinet.danger.delete_reason_placeholder') }}"
                    style="resize: vertical;"
                >{{ old('reason') }}</textarea>
                @error('reason') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="danger_password">{{ __('site.cabinet.danger.current_password') }}</label>
                <input
                    type="password"
                    id="danger_password"
                    name="password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                >
                @error('password') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" style="padding: 12px; background: var(--bg-card); border-radius: 4px;">
                <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.5;">
                    <input
                        type="checkbox"
                        name="confirm"
                        value="1"
                        required
                        style="margin-top: 3px;"
                        {{ old('confirm') ? 'checked' : '' }}
                    >
                    <span>
                        {{ __('site.cabinet.danger.delete_confirm') }}
                    </span>
                </label>
                @error('confirm') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <button
                type="submit"
                class="btn"
                style="background: #dc2626; color: #fff; border-color: #dc2626;"
                onclick="return confirm('{{ __('site.cabinet.danger.delete_confirm_dialog') }}');"
            >
                {{ __('site.cabinet.danger.delete_action') }}
            </button>
        </form>
    </div>
@endif
