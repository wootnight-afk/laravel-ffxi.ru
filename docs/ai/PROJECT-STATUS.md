# Project Status — laravel-ffxi.ru

## HEAD
`a087f73` на ветке `main`.

## Прогресс по этапам (§30 context.md)

| Этап | Название | Статус | Артефакты |
|---|---|---|---|
| 0 | Timeweb audit | pending | — (владелец) |
| 1 | ADR-001 stack | done | docs/adr/ADR-001-stack.md |
| 2 | Docker + skeleton | done | docker-compose.yml, /up |
| 3 | Auth, roles, foundation | done | section.access, Gate::before (scoped) |
| 4 | Content engine | done | news, pages, comments, sanitizer |
| 5 | Gallery | done | ImageProcessor, ProcessPhotoJob |
| 6 | Profiles & cabinet | ACCEPTED | docs/acceptance/stage-6.md |
| 7 | Dashboard | IN PROGRESS | D1 ✅ D2 ✅ D3 ✅ D4 ✅ D5 ✅ D6 ✅ D7 ✅ |
| 8 | Filament | SCAFFOLD ONLY | /admin panel + canAccessPanel |
| 9 | Backup/restore | not started | — |
| 10 | Tests (§9) | partial | 372 passed |
| 11 | CI | not started | — |
| 12 | Timeweb staging | not started | — |
| 13 | Deployment | not started | — |
| 14 | Production | not started | — |
| 15 | Update UI | not started | — |

## Stage 7 — детализация

- D1 ✅ — schema + модели events (`EventType`, `Event`, `EventParticipant`).
  Миграции 000017–000019. Enum EventStatus, EventParticipantStatus.
- D2 ✅ — EventPolicy, EventService, 4 database-notifications,
  EventController, EventParticipantController, Livewire EventsBoard
  и EventSignup, EventCommentController. Миграция 000020 — activities
  (D3).
- D3 ✅ — Activity model, ActivityType, ActivityLogger, 6 событий
  + 6 listeners, ActivityController, /activity, dashboard feed.
- D4 ✅ — Chat (Livewire ChatRoom + модерация). Миграции 000021
  chat_banned_permanently, 000022 chat_messages; database notifications;
  synchronous chat activity.
- D5 ✅ — ActivityBell (community + personal database notifications),
  OnlineUsers widget, 30s/60s polling; activity snapshots скрываются,
  если существующий subject недоступен viewer'у.
- D6 ✅ — data-driven DashboardWidget grid на /players; сортировка,
  активность и column_span из БД; четыре community-типа.
- D7 ✅ — Scheduler: database event reminders за час до начала с
  проверкой актуального starts_at и идемпотентностью; retention
  activities по activity_retention_days через Artisan-команду.

## Контракты, которые НЕЛЬЗЯ менять

- Roles: только user / editor / admin. Никаких owner/moderator/super_admin.
- section.access + guest_sections для матрицы доступа (ADR-007).
- Gate::before для admin — только для section.* (ADR-007).
- Канал уведомлений Stage 7: database-only (ADR-008 §2.6).
- Chat moderation: chat.moderate (admin only, ADR-008 §2.5).
- Mute: chat_banned_until + chat_banned_permanently (решение владельца для D4).
- Retention activity: 180 дней от created_at (ADR-008 §2.3).
- api_chart / html_board — Stage 8 (ADR-008 §2.8).
- Пакеты: только из context.md §3 (уже установлены).

## Известные gaps (зафиксированы, не блокеры)

См. docs/acceptance/stage-6.md §5.

## Порядок дальнейшей работы

1. Приёмка Stage 7 + отчёт docs/acceptance/stage-7.md.
2. Stage 8 — Filament (12 Resources + 4 Pages).
3. Stages 9–15 — по §30 context.md.
