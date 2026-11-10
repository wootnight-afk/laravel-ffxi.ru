<?php

namespace App\Enums;

enum ActivityType: string
{
    case Registered = 'registered';
    case NewsPublished = 'news_published';
    case PhotoPublished = 'photo_published';
    case EventCreated = 'event_created';
    case EventJoined = 'event_joined';
    case CommentCreated = 'comment_created';
    case ChatMessage = 'chat_message';

    public function icon(): string
    {
        return match ($this) {
            self::Registered => '🎉',
            self::NewsPublished => '📰',
            self::PhotoPublished => '🖼',
            self::EventCreated => '📋',
            self::EventJoined => '✅',
            self::CommentCreated => '💬',
            self::ChatMessage => '💬',
        };
    }

    public function label(): string
    {
        return __('site.activity.types.'.$this->value);
    }
}
