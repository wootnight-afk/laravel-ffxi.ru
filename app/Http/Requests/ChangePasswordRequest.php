<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => __('site.cabinet.validation.current_password_required'),
            'current_password.current_password' => __('site.cabinet.validation.current_password_invalid'),
            'password.required' => __('site.cabinet.validation.new_password_required'),
            'password.confirmed' => __('site.cabinet.validation.password_confirmed'),
        ];
    }
}
