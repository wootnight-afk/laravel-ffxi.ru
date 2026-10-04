<?php

namespace App\Livewire;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ActivityBell extends Component
{
    public string $activeTab = 'community';

    public function mount(): void
    {
        $this->authenticatedUser();
    }

    public function refreshBell(): void
    {
        $this->authenticatedUser();
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, ['community', 'personal'], true), 404);
        $this->authenticatedUser();
        $this->activeTab = $tab;
    }

    public function markCommunityRead(): void
    {
        $user = $this->authenticatedUser();
        $user->forceFill(['last_activity_seen_at' => now()])->saveQuietly();
    }

    public function openNotification(string $notificationId): ?RedirectResponse
    {
        $user = $this->authenticatedUser();
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();

        $url = $this->safeNotificationUrl($notification->data['url'] ?? null);

        return $url === null ? null : redirect()->to($url);
    }

    public function render(): View
    {
        $user = $this->authenticatedUser();
        abort_unless(in_array($this->activeTab, ['community', 'personal'], true), 403);

        $seenAt = $user->last_activity_seen_at;
        $communityQuery = Activity::query()
            ->when(
                $seenAt !== null,
                fn ($query) => $query->where('created_at', '>', $seenAt),
            );
        $unreadCommunityCount = (clone $communityQuery)->count();

        $activities = (clone $communityQuery)
            ->recent()
            ->forFeed()
            ->limit(15)
            ->get();

        $unreadPersonalCount = $user->unreadNotifications()->count();
        $notifications = $user->notifications()->latest()->limit(15)->get();
        $notificationActors = $this->notificationActors($notifications);
        $notificationUrls = $notifications
            ->mapWithKeys(fn (DatabaseNotification $notification) => [
                $notification->id => $this->safeNotificationUrl($notification->data['url'] ?? null),
            ])
            ->filter()
            ->all();

        return view('livewire.activity-bell', [
            'activities' => $activities,
            'notifications' => $notifications,
            'notificationActors' => $notificationActors,
            'notificationUrls' => $notificationUrls,
            'unreadCommunityCount' => $unreadCommunityCount,
            'unreadPersonalCount' => $unreadPersonalCount,
            'unreadCount' => $unreadCommunityCount + $unreadPersonalCount,
            'viewer' => $user,
        ]);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @param  EloquentCollection<int, DatabaseNotification>  $notifications
     * @return array<int, User>
     */
    private function notificationActors(EloquentCollection $notifications): array
    {
        $actorIds = $notifications
            ->flatMap(fn (DatabaseNotification $notification) => [
                data_get($notification->data, 'actor.id'),
                data_get($notification->data, 'participant.id'),
                data_get($notification->data, 'sender.id'),
                data_get($notification->data, 'moderator.id'),
            ])
            ->filter(fn (mixed $id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id) => (int) $id)
            ->unique()
            ->values();

        return User::query()->whereKey($actorIds)->get()->keyBy('id')->all();
    }

    private function safeNotificationUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x1F]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        if (! isset($parts['host'])) {
            $path = $parts['path'] ?? '';

            return str_starts_with($path, '/') && ! str_starts_with($path, '//')
                ? $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '')
                : null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $requestHost = request()->getHost();

        if (
            ! in_array(strtolower($parts['host']), array_filter([strtolower((string) $appHost), strtolower($requestHost)]), true)
            || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        ) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
