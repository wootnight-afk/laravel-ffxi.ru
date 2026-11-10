<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePlayerNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', News::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:20', 'max:50000'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'comments_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('site.cabinet.validation.news_title_required'),
            'title.min' => __('site.cabinet.validation.news_title_min'),
            'title.max' => __('site.cabinet.validation.news_title_max'),
            'body.required' => __('site.cabinet.validation.news_body_required'),
            'body.min' => __('site.cabinet.validation.news_body_min'),
            'body.max' => __('site.cabinet.validation.news_body_max'),
            'excerpt.max' => __('site.cabinet.validation.news_excerpt_max'),
        ];
    }
}
