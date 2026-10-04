<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Album;
use App\Models\Comment;
use App\Models\Event;
use App\Models\News;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class ActivitySubjectResolver
{
    public function url(Activity $activity, ?User $viewer): ?string
    {
        if ($viewer === null || ! $activity->subject instanceof Model) {
            return null;
        }

        return match (true) {
            $activity->subject instanceof News => $this->newsUrl($activity->subject, $viewer),
            $activity->subject instanceof Photo => $this->photoUrl($activity->subject, $viewer),
            $activity->subject instanceof Event => $this->eventUrl($activity->subject, $viewer),
            $activity->subject instanceof Comment => $this->commentUrl($activity->subject, $viewer),
            $activity->subject instanceof User => $this->userUrl($activity->subject, $viewer),
            default => null,
        };
    }

    public function subjectAccessible(Activity $activity, ?User $viewer): bool
    {
        if ($activity->subject_id === null) {
            return true;
        }

        if (! $activity->subject instanceof Model) {
            return false;
        }

        return $this->url($activity, $viewer) !== null;
    }

    public function actorLinkable(?User $actor, ?User $viewer): bool
    {
        return $actor !== null
            && $viewer !== null
            && $this->canViewSection($viewer, 'player_profiles')
            && Gate::forUser($viewer)->allows('view', $actor)
            && Route::has('players.show');
    }

    private function newsUrl(News $news, User $viewer): ?string
    {
        if (! Gate::forUser($viewer)->allows('view', $news)) {
            return null;
        }

        if ($news->isSite()) {
            return $news->isPublished()
                && $this->canViewSection($viewer, 'news')
                && Route::has('news.show')
                ? route('news.show', $news->slug)
                : null;
        }

        $author = $news->user;

        return $news->isPublished()
            && $author !== null
            && $this->canViewSection($viewer, 'player_profiles')
            && ($author->isProfilePublic() || $viewer->id === $author->id || $viewer->isAdmin())
            && Route::has('players.show')
            ? route('players.show', $author->name)
            : null;
    }

    private function photoUrl(Photo $photo, User $viewer): ?string
    {
        if (! Gate::forUser($viewer)->allows('view', $photo) || ! $photo->is_published) {
            return null;
        }

        $album = $photo->album;
        if (
            ! $album instanceof Album
            || ! $album->isSite()
            || ! $album->is_published
            || ! $this->canViewSection($viewer, 'gallery')
            || ! Route::has('gallery.photo')
        ) {
            return null;
        }

        return route('gallery.photo', [$album->slug, $photo->getKey()]);
    }

    private function eventUrl(Event $event, User $viewer): ?string
    {
        if (
            ! Gate::forUser($viewer)->allows('view', $event)
            || ! $this->canViewSection($viewer, 'events')
            || ! Route::has('events.show')
        ) {
            return null;
        }

        return route('events.show', $event);
    }

    private function commentUrl(Comment $comment, User $viewer): ?string
    {
        if ($comment->status !== Comment::STATUS_APPROVED) {
            return null;
        }

        $target = $comment->commentable;
        if ($target instanceof Event) {
            $url = $this->eventUrl($target, $viewer);
        } elseif ($target instanceof News) {
            $url = $this->newsUrl($target, $viewer);
        } elseif ($target instanceof Photo) {
            $url = $this->photoUrl($target, $viewer);
        } else {
            return null;
        }

        return $url === null ? null : $url.'#comment-'.$comment->getKey();
    }

    private function userUrl(User $user, User $viewer): ?string
    {
        return $this->actorLinkable($user, $viewer)
            ? route('players.show', $user->name)
            : null;
    }

    private function canViewSection(User $viewer, string $section): bool
    {
        return $viewer->isAdmin() || $viewer->can("section.{$section}.view");
    }
}
