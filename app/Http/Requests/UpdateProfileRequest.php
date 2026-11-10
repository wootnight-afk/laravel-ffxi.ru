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
            'race.in' => __('site.cabinet.validation.race_invalid'),
            'main_job.max' => __('site.cabinet.validation.main_job_max'),
            'legend.max' => __('site.cabinet.validation.legend_max'),
            'phone.max' => __('site.cabinet.validation.phone_max'),
        ];
    }
}
