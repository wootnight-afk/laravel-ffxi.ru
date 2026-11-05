<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimezonePreferenceRequest extends FormRequest
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
            'timezone' => ['present', 'nullable', 'string', 'max:64', 'timezone'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timezone.present' => 'Выберите часовой пояс или режим «авто».',
            'timezone.string' => 'Некорректное значение часового пояса.',
            'timezone.max' => 'Идентификатор часового пояса слишком длинный.',
            'timezone.timezone' => 'Укажите корректный часовой пояс (IANA).',
        ];
    }
}
