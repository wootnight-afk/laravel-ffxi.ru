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
            'body.required' => __('site.comments.body_required'),
            'body.min' => __('site.comments.body_min'),
            'body.max' => __('site.comments.body_max'),
            'parent_id.exists' => __('site.comments.parent_missing'),
        ];
    }
}
