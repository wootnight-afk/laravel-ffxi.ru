<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership + permission checked by AlbumPolicy::update() in the controller.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('site.cabinet.validation.album_title_required'),
            'title.min' => __('site.cabinet.validation.album_title_min'),
            'title.max' => __('site.cabinet.validation.album_title_max'),
            'description.max' => __('site.cabinet.validation.album_description_max'),
        ];
    }
}
