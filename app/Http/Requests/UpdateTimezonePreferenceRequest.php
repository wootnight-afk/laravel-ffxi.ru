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
            'timezone.present' => __('site.cabinet.validation.timezone_present'),
            'timezone.string' => __('site.cabinet.validation.timezone_string'),
            'timezone.max' => __('site.cabinet.validation.timezone_max'),
            'timezone.timezone' => __('site.cabinet.validation.timezone_timezone'),
        ];
    }
}
