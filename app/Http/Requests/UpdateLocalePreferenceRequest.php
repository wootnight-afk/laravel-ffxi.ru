<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocalePreferenceRequest extends FormRequest
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
            'locale' => ['present', 'nullable', 'string', 'max:16', Rule::in(Locale::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locale.present' => 'Выберите язык интерфейса или режим «авто».',
            'locale.string' => 'Некорректное значение языка интерфейса.',
            'locale.max' => 'Код языка слишком длинный.',
            'locale.in' => 'Недопустимый язык интерфейса.',
        ];
    }
}
