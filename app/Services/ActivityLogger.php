<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function log(
        ActivityType $type,
        ?User $actor,
        ?Model $subject,
        array $data = [],
    ): Activity {
        $activity = new Activity([
            'type' => $type,
            'data' => $data,
            'created_at' => now(),
        ]);

        if ($actor !== null) {
            $activity->actor()->associate($actor);
        }

        if ($subject !== null) {
            $activity->subject()->associate($subject);
        }

        $activity->save();

        return $activity;
    }
}
