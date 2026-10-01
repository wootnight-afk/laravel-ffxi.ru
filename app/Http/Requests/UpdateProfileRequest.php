<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'race' => ['nullable', 'string', 'in:hume,elvaan,tarutaru,mithra,galka'],
            'main_job' => ['nullable', 'string', 'max:10'],
            'legend' => ['nullable', 'string', 'max:4000'],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_is_public' => ['nullable', 'boolean'],
            'is_profile_public' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'race.in' => 'Недопустимая раса.',
            'main_job.max' => 'Название профессии слишком длинное.',
            'legend.max' => 'Легенда не должна превышать 4000 символов.',
            'phone.max' => 'Телефон слишком длинный.',
        ];
    }
}
