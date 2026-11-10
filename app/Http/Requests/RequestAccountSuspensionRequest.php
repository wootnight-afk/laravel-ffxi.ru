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
            'password.required' => __('site.cabinet.validation.current_password_required'),
            'password.current_password' => __('site.cabinet.validation.current_password_invalid'),
            'reason.required' => __('site.cabinet.validation.reason_required'),
            'reason.min' => __('site.cabinet.validation.reason_min'),
            'reason.max' => __('site.cabinet.validation.reason_max'),
            'confirm.required' => __('site.cabinet.validation.confirm_required'),
            'confirm.accepted' => __('site.cabinet.validation.confirm_required'),
        ];
    }
}
