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
        return match ($this) {
            self::Registered => 'зарегистрировался',
            self::NewsPublished => 'опубликовал новость',
            self::PhotoPublished => 'опубликовал фото',
            self::EventCreated => 'создал событие',
            self::EventJoined => 'записался на событие',
            self::CommentCreated => 'оставил комментарий',
            self::ChatMessage => 'отправил сообщение в чат',
        };
    }
}
