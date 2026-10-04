<div class="activity-bell" wire:poll.30s="refreshBell" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <button
        class="activity-bell__trigger"
        type="button"
        aria-label="Уведомления"
        aria-expanded="false"
        x-bind:aria-expanded="open.toString()"
        x-on:click="open = !open"
    >
        <span aria-hidden="true">🔔</span>
        @if ($unreadCount > 0)
            <span class="activity-bell__badge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </button>

    <section class="activity-bell__dropdown" x-show="open" x-cloak aria-label="Уведомления">
        <div class="activity-bell__tabs" role="tablist">
            <button
                class="activity-bell__tab {{ $activeTab === 'community' ? 'is-active' : '' }}"
                type="button"
                role="tab"
                aria-selected="{{ $activeTab === 'community' ? 'true' : 'false' }}"
                wire:click="selectTab('community')"
            >
                Сообщество
                @if ($unreadCommunityCount > 0)
                    <span>{{ $unreadCommunityCount > 99 ? '99+' : $unreadCommunityCount }}</span>
                @endif
            </button>
            <button
                class="activity-bell__tab {{ $activeTab === 'personal' ? 'is-active' : '' }}"
                type="button"
                role="tab"
                aria-selected="{{ $activeTab === 'personal' ? 'true' : 'false' }}"
                wire:click="selectTab('personal')"
            >
                Личные
                @if ($unreadPersonalCount > 0)
                    <span>{{ $unreadPersonalCount > 99 ? '99+' : $unreadPersonalCount }}</span>
                @endif
            </button>
        </div>

        @if ($activeTab === 'community')
            <div class="activity-bell__list" role="tabpanel">
                @if ($activities->isEmpty())
                    <p class="news-meta activity-bell__empty">Новых событий сообщества нет.</p>
                @else
                    @foreach ($activities as $activity)
                        @php
                            $subjectResolver = app(\App\Services\ActivitySubjectResolver::class);
                            $subjectUrl = $subjectResolver->url($activity, $viewer);
                            $hasSubject = $activity->subject_id !== null;
                            $subjectAccessible = ! $hasSubject
                                || $subjectResolver->subjectAccessible($activity, $viewer);
                            $snapshot = $activity->data['title']
                                ?? $activity->data['target_title']
                                ?? $activity->data['caption']
                                ?? $activity->data['album_title']
                                ?? $activity->data['name']
                                ?? null;
                        @endphp
                        <article class="activity-bell__entry" wire:key="community-notice-{{ $activity->id }}">
                            <span class="activity-bell__icon" aria-hidden="true">{{ $activity->type->icon() }}</span>
                            <div class="activity-bell__entry-body">
                                <p>
                                    <x-activity-actor :actor="$activity->actor" :viewer="$viewer" />
                                    {{ $activity->type->label() }}
                                    @if ($snapshot && $subjectAccessible)
                                        @if ($subjectUrl)
                                            <a href="{{ $subjectUrl }}">{{ $snapshot }}</a>
                                        @else
                                            <span>{{ $snapshot }}</span>
                                        @endif
                                    @elseif (! $snapshot && $subjectAccessible && $subjectUrl)
                                        <a href="{{ $subjectUrl }}">перейти</a>
                                    @endif
                                </p>
                                <time class="news-meta" datetime="{{ $activity->created_at->toIso8601String() }}">
                                    {{ $activity->created_at->diffForHumans() }}
                                </time>
                            </div>
                        </article>
                    @endforeach
                @endif

                @if ($unreadCommunityCount > 0)
                    <button class="activity-bell__mark-read" type="button" wire:click="markCommunityRead">
                        Отметить прочитанными
                    </button>
                @endif
            </div>
        @else
            <div class="activity-bell__list" role="tabpanel">
                @if ($notifications->isEmpty())
                    <p class="news-meta activity-bell__empty">Личных уведомлений пока нет.</p>
                @else
                    @foreach ($notifications as $notification)
                        @php
                            $notificationName = \Illuminate\Support\Str::afterLast($notification->type, '\\');
                            $notificationTitle = match ($notificationName) {
                                'ChatMentionNotification' => 'Упоминание в чате',
                                'ChatBannedNotification' => 'Вы заглушены в чате',
                                'ChatUnbannedNotification' => 'Мут в чате снят',
                                'EventCancelledNotification' => 'Событие отменено',
                                'EventUpdatedNotification' => 'Событие обновлено',
                                'EventParticipantJoinedNotification' => 'Новый участник события',
                                'EventParticipantLeftNotification' => 'Участник покинул событие',
                                'AccountDeletionRequestedNotification' => 'Запрос на удаление аккаунта',
                                'PasswordChangedNotification' => 'Пароль изменён',
                                'UserEmailChangedNotification' => 'Email изменён',
                                default => 'Новое уведомление',
                            };
                            $notificationActor = null;
                            foreach (['actor', 'participant', 'sender', 'moderator'] as $actorKey) {
                                $actorId = data_get($notification->data, "{$actorKey}.id");
                                if (is_numeric($actorId) && isset($notificationActors[(int) $actorId])) {
                                    $notificationActor = $notificationActors[(int) $actorId];
                                    break;
                                }
                            }
                            $subjectTitle = data_get($notification->data, 'event.title')
                                ?? data_get($notification->data, 'news.title')
                                ?? data_get($notification->data, 'photo.caption')
                                ?? data_get($notification->data, 'title');
                        @endphp
                        <article
                            class="activity-bell__entry {{ $notification->read_at === null ? 'is-unread' : '' }}"
                            wire:key="personal-notice-{{ $notification->id }}"
                        >
                            <span class="activity-bell__icon" aria-hidden="true">●</span>
                            <div class="activity-bell__entry-body">
                                @if ($notificationActor)
                                    <p><x-user-identity :user="$notificationActor" context="activity" /> {{ $notificationTitle }}</p>
                                @else
                                    <p>{{ $notificationTitle }}</p>
                                @endif
                                @if ($subjectTitle)
                                    <p class="news-meta">{{ $subjectTitle }}</p>
                                @endif
                                <time class="news-meta" datetime="{{ $notification->created_at->toIso8601String() }}">
                                    {{ $notification->created_at->diffForHumans() }}
                                </time>
                                @if (isset($notificationUrls[$notification->id]))
                                    <button
                                        class="activity-bell__open"
                                        type="button"
                                        wire:click="openNotification('{{ $notification->id }}')"
                                    >Открыть</button>
                                @else
                                    <button
                                        class="activity-bell__open"
                                        type="button"
                                        wire:click="openNotification('{{ $notification->id }}')"
                                    >Отметить прочитанным</button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                @endif
            </div>
        @endif
    </section>
</div>
