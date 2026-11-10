<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Album;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadPhotosRequest extends FormRequest
{
    /** Maximum number of photos that may be uploaded in a single batch. */
    public const MAX_BATCH = 10;

    /** Per-file maximum size in kilobytes (matches gallery_max_photo_mb = 8). */
    public const MAX_FILE_KB = 8192;

    public function authorize(): bool
    {
        /** @var Album|null $album */
        $album = $this->route('album');

        return $album !== null && Gate::allows('update', $album);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'photos.*' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp,gif',
                'max:'.self::MAX_FILE_KB,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.required' => __('site.cabinet.validation.photos_required'),
            'photos.array' => __('site.cabinet.validation.photos_array'),
            'photos.min' => __('site.cabinet.validation.photos_min'),
            'photos.max' => __('site.cabinet.validation.photos_max', ['max' => self::MAX_BATCH]),
            'photos.*.file' => __('site.cabinet.validation.photo_file'),
            'photos.*.mimes' => __('site.cabinet.validation.photo_mimes'),
            'photos.*.max' => __('site.cabinet.validation.photo_max'),
        ];
    }
}
