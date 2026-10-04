<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\ActivityFeedGrouper;
use Illuminate\Contracts\View\View;

class ActivityController extends Controller
{
    public function index(ActivityFeedGrouper $grouper): View
    {
        $activities = Activity::query()
            ->recent()
            ->forFeed()
            ->paginate(50);

        return view('activity.index', [
            'activities' => $activities,
            'groups' => $grouper->group($activities->getCollection()),
            'viewer' => request()->user(),
        ]);
    }
}
