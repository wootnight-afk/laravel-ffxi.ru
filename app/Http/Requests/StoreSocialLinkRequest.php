<?php

namespace App\Http\Requests;

use App\Services\SocialLinkService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialLinkRequest extends FormRequest
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
        /** @var SocialLinkService $service */
        $service = app(SocialLinkService::class);

        return [
            'type' => ['required', 'string', Rule::in($service->allowedPlatforms())],
            'username' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'string', 'max:500'],
            'label' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Выберите платформу.',
            'type.in' => 'Неизвестная платформа.',
            'username.max' => 'Username слишком длинный.',
            'url.max' => 'Ссылка слишком длинная.',
            'label.max' => 'Название слишком длинное.',
        ];
    }
}
