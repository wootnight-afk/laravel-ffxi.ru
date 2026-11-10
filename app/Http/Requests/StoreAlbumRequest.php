<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Album;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Album::class);
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
