@extends('layouts.app')

@section('title', 'Регистрация')

@section('content')
    <h1 class="page-title">Регистрация</h1>

    <form
        method="POST"
        action="{{ route('register.store') }}"
        x-data="registrationForm()"
    >
        @csrf

        <div class="form-group">
            <label for="name">Ник</label>
            <input
                type="text"
                id="name"
                name="name"
                class="form-control"
                value="{{ old('name') }}"
                required
                autocomplete="username"
                minlength="3"
                maxlength="24"
                x-on:input.debounce.500ms="checkNickname($event.target.value)"
            >
            @error('name')
                <div class="form-error">{{ $message }}</div>
            @enderror
            <div x-show="status === 'checking'" style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Проверяем…
            </div>
            <div x-show="status === 'available'" style="font-size: 13px; color: #15803d; margin-top: 4px;">
                ✓ Ник свободен
            </div>
            <div x-show="status === 'taken'" style="font-size: 13px; color: #b45309; margin-top: 4px;">
                Этот ник занят. Возможно, подойдут:
                <div style="margin-top: 6px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <template x-for="s in suggestions" :key="s">
                        <button
                            type="button"
                            class="btn"
                            style="padding: 4px 10px; font-size: 13px;"
                            x-on:click="pickSuggestion(s)"
                            x-text="s"
                        ></button>
                    </template>
                </div>
            </div>
            <div x-show="status === 'invalid' || status === 'blacklisted'" style="font-size: 13px; color: #dc2626; margin-top: 4px;">
                Ник не подходит.
            </div>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                value="{{ old('email') }}"
                required
                autocomplete="email"
            >
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                required
                autocomplete="new-password"
                minlength="8"
            >
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">Пароль ещё раз</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                required
                autocomplete="new-password"
            >
        </div>

        <div class="form-group">
            <label for="phone">Телефон (необязательно)</label>
            <input
                type="text"
                id="phone"
                name="phone"
                class="form-control"
                value="{{ old('phone') }}"
                maxlength="32"
            >
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                Виден только администрации. По умолчанию не отображается в профиле.
            </div>
            @error('phone')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="max-width: 600px;">
            <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.5;">
                <input type="checkbox" name="pd_consent" value="1" {{ old('pd_consent') ? 'checked' : '' }} required style="margin-top: 3px;">
                <span>
                    Согласен на обработку персональных данных в соответствии
                    с <a href="{{ route('privacy') }}" target="_blank">Политикой обработки ПД</a>.
                </span>
            </label>
            @error('pd_consent')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group" style="max-width: 600px;">
            <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.5;">
                <input type="checkbox" name="marketing_consent" value="1" {{ old('marketing_consent') ? 'checked' : '' }} style="margin-top: 3px;">
                <span>
                    Хочу получать новости и уведомления от сообщества по email (необязательно).
                </span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary" x-bind:disabled="status === 'taken' || status === 'invalid' || status === 'blacklisted'">
            Зарегистрироваться
        </button>

        <div style="margin-top: 16px; font-size: 13px;">
            Уже есть аккаунт? <a href="{{ route('login') }}">Войти</a>
        </div>
    </form>

    @push('scripts')
        <script>
            function registrationForm() {
                return {
                    status: null,
                    suggestions: [],
                    async checkNickname(value) {
                        if (! value || value.length < 3) {
                            this.status = null;
                            this.suggestions = [];
                            return;
                        }

                        this.status = 'checking';

                        try {
                            const response = await fetch('{{ route('api.nickname.check') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({ name: value }),
                            });

                            if (! response.ok) {
                                this.status = null;
                                this.suggestions = [];
                                return;
                            }

                            const data = await response.json();
                            this.suggestions = data.suggestions ?? [];

                            if (data.available) {
                                this.status = 'available';
                            } else {
                                this.status = data.reason ?? 'invalid';
                            }
                        } catch (e) {
                            this.status = null;
                            this.suggestions = [];
                        }
                    },
                    pickSuggestion(nickname) {
                        document.getElementById('name').value = nickname;
                        this.status = 'available';
                        this.suggestions = [];
                    },
                };
            }
        </script>
    @endpush
@endsection
