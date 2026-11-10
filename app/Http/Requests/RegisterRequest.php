<?php

namespace App\Http\Requests;

use App\Services\NicknameSuggester;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    /** @var NicknameSuggester $suggester */
                    $suggester = app(NicknameSuggester::class);
                    $result = $suggester->check((string) $value);

                    if ($result['available']) {
                        return;
                    }

                    $fail(match ($result['reason']) {
                        'invalid' => __('site.auth.nickname.invalid'),
                        'blacklisted' => __('site.auth.nickname.blacklisted'),
                        'taken' => __('site.auth.nickname.taken'),
                        default => __('site.auth.nickname.not_allowed'),
                    });
                },
            ],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:32'],
            'pd_consent' => ['required', 'accepted'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('site.auth.validation.name_required'),
            'email.required' => __('site.auth.validation.email_required'),
            'email.email' => __('site.auth.validation.email_invalid'),
            'email.unique' => __('site.auth.validation.email_taken'),
            'password.required' => __('site.auth.validation.password_required'),
            'password.confirmed' => __('site.auth.validation.password_confirmed'),
            'pd_consent.required' => __('site.auth.validation.pd_consent_required'),
            'pd_consent.accepted' => __('site.auth.validation.pd_consent_required'),
        ];
    }
}
