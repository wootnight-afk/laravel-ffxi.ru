<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ChatMentionParser
{
    /**
     * @param  iterable<ChatMessage>  $messages
     * @return array<string, User>
     */
    public function usersForMessages(iterable $messages): array
    {
        $handles = [];

        foreach ($messages as $message) {
            foreach ($this->handles($message->body) as $handle) {
                $handles[mb_strtolower($handle)] = $handle;
            }
        }

        if ($handles === []) {
            return [];
        }

        return User::query()
            ->whereIn('name', array_values($handles))
            ->get()
            ->keyBy(fn (User $user): string => mb_strtolower($user->name))
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(string $body, User $sender): Collection
    {
        $handles = $this->handles($body);
        if ($handles === []) {
            return new Collection;
        }

        $users = User::query()
            ->whereIn('name', $handles)
            ->whereKeyNot($sender->getKey())
            ->get()
            ->keyBy(fn (User $user): string => mb_strtolower($user->name));
        $recipients = new Collection;

        foreach ($handles as $handle) {
            $user = $users->get(mb_strtolower($handle));
            if ($user instanceof User) {
                $recipients->push($user);
            }
        }

        return $recipients;
    }

    /**
     * @param  array<string, User>  $users
     * @return list<array{type: 'text'|'mention', text: string, user?: User}>
     */
    public function segments(string $body, array $users, ?User $sender): array
    {
        preg_match_all('/@([a-zA-Z0-9_-]{3,24})/', $body, $matches, PREG_OFFSET_CAPTURE);

        $segments = [];
        $offset = 0;
        $used = [];

        foreach ($matches[0] as $index => [$match, $matchOffset]) {
            $handle = $matches[1][$index][0];
            $user = $users[mb_strtolower($handle)] ?? null;
            $isMention = $user instanceof User
                && ($sender === null || $user->isNot($sender))
                && (isset($used[mb_strtolower($handle)]) || count($used) < 5);

            if ($matchOffset > $offset) {
                $segments[] = [
                    'type' => 'text',
                    'text' => substr($body, $offset, $matchOffset - $offset),
                ];
            }

            if ($isMention) {
                $segments[] = [
                    'type' => 'mention',
                    'text' => $match,
                    'user' => $user,
                ];
                $used[mb_strtolower($handle)] = true;
            } else {
                $segments[] = ['type' => 'text', 'text' => $match];
            }

            $offset = $matchOffset + strlen($match);
        }

        if ($offset < strlen($body)) {
            $segments[] = ['type' => 'text', 'text' => substr($body, $offset)];
        }

        return $segments;
    }

    /**
     * @return list<string>
     */
    private function handles(string $body): array
    {
        preg_match_all('/@([a-zA-Z0-9_-]{3,24})/', $body, $matches);

        $handles = [];
        foreach ($matches[1] as $handle) {
            $handles[mb_strtolower($handle)] ??= $handle;
            if (count($handles) >= 5) {
                break;
            }
        }

        return array_values($handles);
    }
}
