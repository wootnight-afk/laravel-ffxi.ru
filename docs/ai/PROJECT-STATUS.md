# Project Status — laravel-ffxi.ru

## HEAD
`404631b` на ветке `main`.

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
| 7 | Dashboard | IN PROGRESS | D1 ✅ D2 ✅ D3 ✅ D4 🟡 D5–D7 ⏳ |
| 8 | Filament | SCAFFOLD ONLY | /admin panel + canAccessPanel |
| 9 | Backup/restore | not started | — |
| 10 | Tests (§9) | partial | 311 passed |
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
- D4 🟡 — Chat (Livewire ChatRoom + модерация). Контракт согласован,
  реализация НЕ начата. Миграции 000021 chat_banned_permanently,
  000022 chat_messages — запланированы.
- D5 ⏳ — Bell (уведомления: личные + community), online users widget.
- D6 ⏳ — DashboardWidget grid (community_chat, events_board,
  activity_feed, online_users + api_chart/html_board из Stage 8).
- D7 ⏳ — Scheduler (event reminder, activity retention).

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

1. D4 — Chat (см. MASTER-TASK.md §Текущий контракт).
2. D5 — Bell + online users.
3. D6 — DashboardWidget grid.
4. D7 — Scheduler + retention.
5. Приёмка Stage 7 + отчёт docs/acceptance/stage-7.md.
6. Stage 8 — Filament (12 Resources + 4 Pages).
7. Stages 9–15 — по §30 context.md.
