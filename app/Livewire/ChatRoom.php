<?php

namespace App\Livewire;

use App\Events\ChatMessageSentForActivity;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\ChatBannedNotification;
use App\Notifications\ChatMentionNotification;
use App\Notifications\ChatUnbannedNotification;
use App\Services\ChatMentionParser;
use App\Services\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ChatRoom extends Component
{
    /** @var array<int, int> */
    public array $messageIds = [];

    public int $lastId = 0;

    public bool $isAtBottom = true;

    public int $newMessageCount = 0;

    public string $body = '';

    public string $deleteReason = '';

    public string $banDuration = '1h';

    public int $pollingInterval = 5;

    public function mount(SettingsRepository $settings): void
    {
        abort_unless($settings->bool('chat_enabled', true), 404);

        $this->pollingInterval = min(30, max(3, $settings->int('chat_polling_interval', 5)));
        $this->messageIds = array_reverse(
            ChatMessage::query()->recent()->limit(50)->pluck('id')->all(),
        );
        $this->lastId = $this->messageIds === [] ? 0 : max($this->messageIds);
    }

    public function pollMessages(SettingsRepository $settings): void
    {
        abort_unless($settings->bool('chat_enabled', true), 404);

        $newIds = ChatMessage::query()
            ->where('id', '>', $this->lastId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($newIds === []) {
            return;
        }

        $this->messageIds = array_values(array_unique([...$this->messageIds, ...$newIds]));
        $this->lastId = max($newIds);

        if ($this->isAtBottom) {
            $this->newMessageCount = 0;
        } else {
            $this->newMessageCount += count($newIds);
        }

        $this->dispatch('chat-new-messages', count: count($newIds), scroll: $this->isAtBottom);
    }

    public function loadMore(SettingsRepository $settings): void
    {
        abort_unless($settings->bool('chat_enabled', true), 404);

        $oldestId = $this->messageIds === [] ? null : min($this->messageIds);
        if ($oldestId === null) {
            return;
        }

        $olderIds = ChatMessage::query()
            ->where('id', '<', $oldestId)
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('id')
            ->all();

        if ($olderIds !== []) {
            $this->messageIds = array_values(array_unique([
                ...array_reverse($olderIds),
                ...$this->messageIds,
            ]));
            $this->dispatch('chat-history-loaded');
        }
    }

    public function postMessage(
        SettingsRepository $settings,
        ChatMentionParser $mentions,
    ): void {
        abort_unless($settings->bool('chat_enabled', true), 404);

        $sender = $this->authenticatedUser();
        Gate::authorize('send', ChatMessage::class);
        $validated = $this->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);
        $key = 'chat-message:'.$sender->getKey();
        $message = null;

        $sent = RateLimiter::attempt($key, 5, function () use (
            $sender,
            $validated,
            $mentions,
            &$message,
        ): bool {
            DB::transaction(function () use ($sender, $validated, $mentions, &$message): void {
                $message = ChatMessage::create([
                    'user_id' => $sender->getKey(),
                    'body' => $validated['body'],
                ]);
                $recipients = $mentions->recipients($message->body, $sender);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new ChatMentionNotification($message, $sender));
                }

                DB::afterCommit(fn () => event(new ChatMessageSentForActivity($message, $sender)));
            });

            return true;
        }, 30);

        if (! $sent || ! $message instanceof ChatMessage) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'body' => "Слишком часто. Повторите через {$seconds} сек.",
            ]);
        }

        $this->messageIds[] = $message->getKey();
        $this->lastId = max($this->lastId, $message->getKey());
        $this->body = '';
        $this->newMessageCount = 0;
        $this->dispatch('chat-message-posted');
    }

    public function deleteMessage(
        int $messageId,
        SettingsRepository $settings,
    ): void {
        abort_unless($settings->bool('chat_enabled', true), 404);

        $message = ChatMessage::query()->findOrFail($messageId);
        Gate::authorize('delete', $message);
        $validated = $this->validate([
            'deleteReason' => ['nullable', 'string', 'max:100'],
        ]);

        $message->forceFill([
            'is_deleted' => true,
            'deleted_by_user_id' => $this->authenticatedUser()->getKey(),
            'deleted_reason' => $validated['deleteReason'] ?: null,
            'body' => '',
        ])->save();

        $this->deleteReason = '';
    }

    public function banUser(
        int $targetUserId,
        SettingsRepository $settings,
    ): void {
        abort_unless($settings->bool('chat_enabled', true), 404);

        $moderator = $this->authenticatedUser();
        $target = User::query()->findOrFail($targetUserId);
        Gate::authorize('ban', [ChatMessage::class, $target]);
        $validated = $this->validate([
            'banDuration' => ['required', 'in:1h,1d,7d,permanent'],
        ]);
        $permanent = $validated['banDuration'] === 'permanent';
        $until = match ($validated['banDuration']) {
            '1h' => now()->addHour(),
            '1d' => now()->addDay(),
            '7d' => now()->addDays(7),
            default => null,
        };

        $target->forceFill([
            'chat_banned_permanently' => $permanent,
            'chat_banned_until' => $until,
        ])->save();
        $target->notify(new ChatBannedNotification(
            $moderator,
            $until?->toIso8601String(),
            $permanent,
        ));
    }

    public function unbanUser(
        int $targetUserId,
        SettingsRepository $settings,
    ): void {
        abort_unless($settings->bool('chat_enabled', true), 404);

        $moderator = $this->authenticatedUser();
        $target = User::query()->findOrFail($targetUserId);
        Gate::authorize('unban', [ChatMessage::class, $target]);
        $wasBanned = $target->isChatBanned();
        $target->forceFill([
            'chat_banned_permanently' => false,
            'chat_banned_until' => null,
        ])->save();

        if ($wasBanned) {
            $target->notify(new ChatUnbannedNotification($moderator));
        }
    }

    public function scrollToBottom(): void
    {
        $this->newMessageCount = 0;
        $this->isAtBottom = true;
        $this->dispatch('chat-scroll-bottom');
    }

    public function render(
        SettingsRepository $settings,
        ChatMentionParser $mentions,
    ): View {
        $viewer = Auth::user();
        $messages = $this->messageIds === []
            ? new Collection
            : ChatMessage::query()
                ->with(['user', 'deletedBy'])
                ->whereIn('id', $this->messageIds)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

        return view('livewire.chat-room', [
            'messages' => $messages,
            'mentionUsers' => $mentions->usersForMessages($messages),
            'viewer' => $viewer,
            'chatEnabled' => $settings->bool('chat_enabled', true),
            'isMuted' => $viewer instanceof User && $viewer->isChatBanned(),
            'canModerate' => $viewer instanceof User && $viewer->can('chat.moderate'),
        ]);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
