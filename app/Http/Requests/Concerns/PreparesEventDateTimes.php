<?php

namespace App\Http\Requests\Concerns;

use App\Models\Event;
use App\Services\TimezoneOptions;
use App\Services\TimezoneResolver;
use App\Support\EventDateInput;
use Illuminate\Validation\ValidationException;

trait PreparesEventDateTimes
{
    /**
     * Converts the declared local event time into UTC (ADR-011 §2.7).
     *
     * The timezone is taken from the explicit form value, then from the event
     * being edited, and finally from the organizer preference. Non-existent
     * and ambiguous local times are rejected instead of being normalized
     * silently by PHP.
     *
     * Both event forms preselect the stored timezone, or UTC for a legacy
     * event that has none. A submitted value identical to that preselection is
     * therefore not a declaration: it must not rewrite the stored column, so
     * legacy `NULL` survives an ordinary edit (ADR-011 §2.7, §8.1).
     */
    protected function prepareEventDateTimes(): void
    {
        $routeEvent = $this->route('event');
        $event = $routeEvent instanceof Event ? $routeEvent : null;
        $timezone = $this->resolveEventTimezone();

        foreach (['starts_at', 'registration_close'] as $field) {
            $value = $this->input($field);

            if (! is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
                continue;
            }

            // Keep the original local value for form repopulation after a
            // validation error; the merged UTC value is what gets validated.
            $this->merge([$this->localInputKey($field) => $value]);

            // An invalid explicit timezone is left untouched for the
            // `timezone` validation rule; converting with a fallback would
            // hide the error.
            if ($timezone === null) {
                continue;
            }

            $normalized = EventDateInput::normalize($value);

            if ($normalized === null) {
                continue;
            }

            [$utc, $error] = EventDateInput::toUtc($normalized, $timezone);

            if ($error !== null) {
                throw ValidationException::withMessages([
                    $field => EventDateInput::message($error),
                ]);
            }

            if ($utc !== null) {
                $this->merge([$field => $utc]);
            }
        }

        // Persist the declaration timezone only when the request actually
        // declares one. An unchanged preselected value is dropped from the
        // input entirely: leaving it in place would both rewrite a legacy
        // `NULL` and look like a forbidden change on a started event.
        if ($timezone === null) {
            return;
        }

        if (! $event instanceof Event) {
            // A new event always stores the chosen timezone.
            $this->merge(['timezone' => $timezone]);

            return;
        }

        if ($this->declaresTimezone($event)) {
            $this->merge(['timezone' => $timezone]);

            return;
        }

        $this->getInputSource()->remove('timezone');
    }

    /**
     * Whether the request declares a timezone instead of echoing the value the
     * form preselected when the page was opened.
     */
    private function declaresTimezone(Event $event): bool
    {
        $input = $this->input('timezone');

        if (! is_string($input) || trim($input) === '') {
            return false;
        }

        return $input !== $this->preselectedTimezone($event);
    }

    /**
     * The timezone both event forms show when the page is opened.
     */
    private function preselectedTimezone(Event $event): string
    {
        // Legacy events have no declared timezone; interpreting new input in
        // UTC keeps the stored moment stable (ADR-011 §2.7).
        return TimezoneOptions::isValid($event->timezone) ? $event->timezone : 'UTC';
    }

    private function resolveEventTimezone(): ?string
    {
        $input = $this->input('timezone');

        if (is_string($input) && trim($input) !== '') {
            return TimezoneOptions::isValid($input) ? $input : null;
        }

        $event = $this->route('event');

        if ($event instanceof Event) {
            return $this->preselectedTimezone($event);
        }

        return app(TimezoneResolver::class)->resolve($this->user());
    }

    private function localInputKey(string $field): string
    {
        return '_'.$field.'_local';
    }
}
