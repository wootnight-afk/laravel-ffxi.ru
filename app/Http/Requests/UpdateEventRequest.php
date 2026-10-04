<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesEventDateTimes;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    use PreparesEventDateTimes;

    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && ($this->user()?->can('update', $event) ?? false);
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
                'sometimes',
                'required',
                'integer',
                Rule::exists('event_types', 'id')->where('is_active', true),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'starts_at' => ['sometimes', 'required', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'max_participants' => ['sometimes', 'nullable', 'integer', 'min:2', 'max:100'],
            'registration_close' => ['sometimes', 'nullable', 'date', 'before:starts_at'],
        ];
    }
}
