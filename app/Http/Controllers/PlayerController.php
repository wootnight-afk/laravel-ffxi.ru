<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\News;
use App\Models\Photo;
use App\Models\User;
use App\Services\ActivityFeedGrouper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function dashboard(ActivityFeedGrouper $grouper): View
    {
        $activities = Activity::query()
            ->recent()
            ->forFeed()
            ->limit(15)
            ->get();

        return view('players.dashboard', [
            'onlineCount' => User::query()
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '>=', now()->subMinutes(5))
                ->count(),
            'activityGroups' => $grouper->group($activities),
            'viewer' => request()->user(),
        ]);
    }

    public function directory(Request $request): View
    {
        $query = User::query()
            ->where('status', UserStatus::Active);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $users = $query
            ->with('rank')
            ->withCount([
                'news' => fn ($q) => $q->where('status', News::STATUS_PUBLISHED)->where('scope', News::SCOPE_PLAYER),
                'comments',
            ])
            ->orderBy('name')
            ->paginate(24);

        return view('players.directory', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        if ($user->isDeletionRequested()) {
            abort(404);
        }

        $viewer = $request->user();
        $isOwner = $viewer !== null && $viewer->id === $user->id;
        $isAdmin = $viewer !== null && $viewer->isAdmin();
        $hasFullAccess = $isOwner || $isAdmin || $user->isProfilePublic();

        $user->load('rank');

        $playerNews = collect();
        $photos = collect();

        if ($hasFullAccess) {
            $playerNews = News::query()
                ->player()
                ->published()
                ->where('user_id', $user->id)
                ->with('user')
                ->pinnedFirst()
                ->limit(4)
                ->get();

            $photos = Photo::query()
                ->where('user_id', $user->id)
                ->where('is_published', true)
                ->orderByDesc('id')
                ->limit(6)
                ->get();
        }

        return view('players.show', [
            'user' => $user,
            'playerNews' => $playerNews,
            'photos' => $photos,
            'hasFullAccess' => $hasFullAccess,
            'isOwner' => $isOwner,
        ]);
    }
}
