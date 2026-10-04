<?php

namespace App\Http\Requests\Concerns;

use App\Services\SettingsRepository;
use DateTimeImmutable;
use DateTimeZone;

trait PreparesEventDateTimes
{
    protected function prepareEventDateTimes(): void
    {
        $displayTimezone = app(SettingsRepository::class)->string('timezone_display', 'UTC');
        $localTimezone = new DateTimeZone($displayTimezone);
        $utcTimezone = new DateTimeZone('UTC');

        foreach (['starts_at', 'registration_close'] as $field) {
            $value = $this->input($field);

            if (! is_string($value) || $value === '' || str_contains($value, "\0")) {
                continue;
            }

            $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $localTimezone);
            $errors = DateTimeImmutable::getLastErrors();

            if (
                $dateTime === false
                || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $dateTime->format('Y-m-d\TH:i') !== $value
            ) {
                continue;
            }

            $this->merge([
                $field => $dateTime->setTimezone($utcTimezone)->format('Y-m-d H:i:s'),
            ]);
        }
    }
}
