<?php

use App\Events\ChatMessageSentForActivity;
use App\Events\CommentCreatedForActivity;
use App\Events\CommunityEventCreated;
use App\Events\CommunityEventJoined;
use App\Events\NewsPublishedForActivity;
use App\Events\PhotoPublishedForActivity;
use App\Events\UserRegisteredForActivity;
use Illuminate\Events\Dispatcher;

it('registers each activity listener exactly once', function () {
    $events = [
        UserRegisteredForActivity::class,
        NewsPublishedForActivity::class,
        PhotoPublishedForActivity::class,
        CommunityEventCreated::class,
        CommunityEventJoined::class,
        CommentCreatedForActivity::class,
        ChatMessageSentForActivity::class,
    ];
    $dispatcher = app(Dispatcher::class);

    foreach ($events as $event) {
        expect($dispatcher->getListeners($event))
            ->toHaveCount(1, "Expected exactly one listener for {$event}.");
    }
});
