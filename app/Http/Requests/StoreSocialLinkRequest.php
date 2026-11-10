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
            'type.required' => __('site.cabinet.validation.social_type_required'),
            'type.in' => __('site.cabinet.validation.social_type_invalid'),
            'username.max' => __('site.cabinet.validation.social_username_max'),
            'url.max' => __('site.cabinet.validation.social_url_max'),
            'label.max' => __('site.cabinet.validation.social_label_max'),
        ];
    }
}
