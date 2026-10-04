<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Collection;

class ActivityFeedGrouper
{
    /**
     * @param  iterable<Activity>  $activities
     * @return array<int, array{activity: Activity, items: list<Activity>}>
     */
    public function group(iterable $activities): array
    {
        $groups = [];

        foreach ($activities as $activity) {
            $lastIndex = array_key_last($groups);
            if ($lastIndex !== null && $this->canAppend($groups[$lastIndex]['activity'], $activity)) {
                $groups[$lastIndex]['items'][] = $activity;

                continue;
            }

            $groups[] = [
                'activity' => $activity,
                'items' => [$activity],
            ];
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, array{activity: Activity, items: list<Activity>}>
     */
    public function groupCollection(Collection $activities): array
    {
        return $this->group($activities);
    }

    private function canAppend(Activity $previous, Activity $current): bool
    {
        return $previous->type === $current->type
            && $previous->actor_id === $current->actor_id;
    }
}
