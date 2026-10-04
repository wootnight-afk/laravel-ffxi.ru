<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesEventDateTimes;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    use PreparesEventDateTimes;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEventDateTimes();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type_id' => [
                'required',
                'integer',
                Rule::exists('event_types', 'id')->where('is_active', true),
            ],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'max_participants' => ['nullable', 'integer', 'min:2', 'max:100'],
            'registration_close' => ['nullable', 'date', 'before:starts_at'],
        ];
    }
}
