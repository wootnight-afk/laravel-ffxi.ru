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
            'title.required' => 'Введите заголовок.',
            'title.min' => 'Заголовок слишком короткий.',
            'title.max' => 'Заголовок не должен превышать 150 символов.',
            'body.required' => 'Введите текст новости.',
            'body.min' => 'Текст новости слишком короткий (минимум 20 символов).',
            'body.max' => 'Текст новости не должен превышать 50000 символов.',
            'excerpt.max' => 'Анонс не должен превышать 300 символов.',
        ];
    }
}
