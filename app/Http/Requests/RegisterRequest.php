<?php

namespace App\Http\Requests;

use App\Services\NicknameSuggester;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    /** @var NicknameSuggester $suggester */
                    $suggester = app(NicknameSuggester::class);
                    $result = $suggester->check((string) $value);

                    if ($result['available']) {
                        return;
                    }

                    $fail(match ($result['reason']) {
                        'invalid' => 'Ник должен начинаться с буквы; разрешены латиница, цифры, дефис и подчёркивание (3–24 символа).',
                        'blacklisted' => 'Этот ник зарезервирован системой.',
                        'taken' => 'Этот ник уже занят.',
                        default => 'Ник не подходит.',
                    });
                },
            ],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:32'],
            'pd_consent' => ['required', 'accepted'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите ник.',
            'email.required' => 'Укажите email.',
            'email.email' => 'Некорректный email.',
            'email.unique' => 'Этот email уже зарегистрирован.',
            'password.required' => 'Укажите пароль.',
            'password.confirmed' => 'Пароли не совпадают.',
            'pd_consent.required' => 'Нужно согласие на обработку персональных данных.',
            'pd_consent.accepted' => 'Нужно согласие на обработку персональных данных.',
        ];
    }
}
