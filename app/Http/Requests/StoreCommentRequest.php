<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:comments,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Введите текст комментария.',
            'body.min' => 'Комментарий должен содержать минимум 20 символов.',
            'body.max' => 'Комментарий не должен превышать 2000 символов.',
            'parent_id.exists' => 'Ответ на несуществующий комментарий.',
        ];
    }
}
