<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestAccountSuspensionRequest extends FormRequest
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
            'password' => ['required', 'string', 'current_password'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirm' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Введите текущий пароль.',
            'password.current_password' => 'Неверный пароль.',
            'reason.required' => 'Укажите причину.',
            'reason.min' => 'Причина слишком короткая (минимум 3 символа).',
            'reason.max' => 'Причина не должна превышать 500 символов.',
            'confirm.required' => 'Подтвердите, что понимаете последствия.',
            'confirm.accepted' => 'Подтвердите, что понимаете последствия.',
        ];
    }
}
