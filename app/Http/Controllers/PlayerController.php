<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Album;
use App\Models\DashboardWidget;
use App\Models\News;
use App\Models\Photo;
use App\Models\User;
use App\Services\ActivityFeedGrouper;
use App\Services\ServerFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlayerController extends Controller
{
    public function dashboard(ActivityFeedGrouper $grouper): View
    {
        $supportedWidgetTypes = ['community_chat', 'events_board', 'activity_feed', 'online_users'];
        $widgets = DashboardWidget::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(function (DashboardWidget $widget) use ($supportedWidgetTypes): bool {
                if (in_array($widget->type, $supportedWidgetTypes, true)) {
                    return true;
                }

                Log::warning('Skipped unsupported dashboard widget type.', [
                    'widget_id' => $widget->id,
                    'widget_key' => $widget->key,
                    'widget_type' => $widget->type,
                ]);

                return false;
            })
            ->values();

        $activities = Activity::query()
            ->recent()
            ->forFeed()
            ->limit(15)
            ->get();

        return view('players.dashboard', [
            'widgets' => $widgets,
            'activityGroups' => $grouper->group($activities),
            'viewer' => request()->user(),
        ]);
    }

    public function directory(Request $request, ServerFilter $serverFilter): View
    {
        $query = User::query()
            ->where('status', UserStatus::Active);

        // Game-server filter (STAGE-2 §6.1): it narrows the existing account
        // listing to accounts that have a public character on the chosen
        // server. It is a viewing preference of the catalogue and is unrelated
        // to the active character of the viewer.
        $activeServer = $serverFilter->server();

        if ($activeServer !== null) {
            $query->whereHas('gameCharacters', function ($characters) use ($activeServer): void {
                $characters->public()->where('game_server_id', $activeServer->getKey());
            });
        }

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
            'activeServer' => $activeServer,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        if ($user->isDeletionRequested() || $user->isSuspended()) {
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
        }

        if ($hasFullAccess && ($isOwner || $isAdmin || $user->isGalleryPublic())) {
            $photos = Photo::query()
                ->where('user_id', $user->id)
                ->where('is_published', true)
                // Only the personal gallery: the block links to
                // gallery.player.photo, which never resolves site albums.
                ->whereHas('album', function ($query) use ($user, $isOwner, $isAdmin): void {
                    $query->where('scope', Album::SCOPE_PLAYER)
                        ->where('user_id', $user->id);

                    if (! $isOwner && ! $isAdmin) {
                        $query->where('is_published', true);
                    }
                })
                ->with('album')
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
