<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($this->user()?->getKey())
                    ->where(fn (Builder $q) => $q->whereNull('deleted_at')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Введите текущий пароль.',
            'current_password.current_password' => 'Неверный текущий пароль.',
            'email.required' => 'Введите email.',
            'email.email' => 'Некорректный email.',
            'email.max' => 'Email слишком длинный.',
            'email.unique' => 'Этот email уже используется.',
        ];
    }
}
