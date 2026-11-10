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
            'current_password.required' => __('site.cabinet.validation.current_password_required'),
            'current_password.current_password' => __('site.cabinet.validation.current_password_invalid'),
            'email.required' => __('site.cabinet.validation.email_required'),
            'email.email' => __('site.cabinet.validation.email_invalid'),
            'email.max' => __('site.cabinet.validation.email_max'),
            'email.unique' => __('site.cabinet.validation.email_taken'),
        ];
    }
}
