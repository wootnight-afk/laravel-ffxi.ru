<?php

namespace App\Services;

use App\Models\News;
use Illuminate\Support\Carbon;

class NewsPublishedAtResolver
{
    /**
     * Interval applied when several news items share the same date without a time.
     */
    public const STEP_MINUTES = 5;

    /**
     * Resolve the publication timestamp entered in the admin form.
     *
     * A midnight value is treated as "date only": it is placed at the start of
     * that day, or STEP_MINUTES after the latest news of the day when one
     * already exists. Any other time is stored exactly as entered.
     */
    public function resolve(?string $value, ?News $ignore = null): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $date = Carbon::parse($value);

        // The value was not changed while editing: keep it as stored instead of
        // shifting it on every save.
        if ($ignore?->published_at?->equalTo($date)) {
            return $date;
        }

        // An explicit time is stored exactly as entered.
        if ($date->format('H:i:s') !== '00:00:00') {
            return $date;
        }

        $latest = News::query()
            ->whereBetween('published_at', [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ])
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->max('published_at');

        if ($latest === null) {
            return $date->startOfDay();
        }

        return Carbon::parse($latest)->addMinutes(self::STEP_MINUTES);
    }
}
