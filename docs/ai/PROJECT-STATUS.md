# Project Status — laravel-ffxi.ru

## HEAD
`6846c30` на ветке `main` (Stage 9: E9.0 — контракт).

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
| 7 | Dashboard | ACCEPTED | docs/acceptance/stage-7.md |
| 8 | Filament | ACCEPTED | docs/acceptance/stage-8.md; контракт: docs/ai/STAGE-8-CONTRACT.md |
| 9 | Backup/restore | E9.0 done | docs/adr/ADR-004-backup-restore.md, docs/ai/STAGE-9-CONTRACT.md |
| 10 | Tests (§9) | partial | 549 passed |
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
- Приёмка Stage 7 ✅ — `docs/acceptance/stage-7.md`; устранено
  дублирование регистрации activity listeners.

## Stage 8 — детализация

- E1 ✅ — Panel navigation (4 группы) + AdminDashboard со статистикой,
  графиком регистраций за 30 дней, audit preview и shortcuts.
- E2 ✅ — UserResource, RoleResource, GuestResource; suspended account
  workflow (миграция 000024) + account requests UI.
- E7 ✅ — SettingsPage (8 групп), `App\Rules\IpAllowlist`,
  `SettingsRepository::setMany` в транзакции, `admin_2fa_required=false`
  и `admin_ip_allowlist=[]` по умолчанию, re-auth при изменении allowlist.
- E3 ✅ — PermissionsMatrixPage: роли × `section.*.view` + `guest_sections`,
  немедленный cache flush, admin-only.
- E4 ✅ — NewsResource + CommentResource: MarkdownEditor → единый
  `ContentRenderer`/`HtmlSanitizer`, plain-text комментарии, модерация.
- E5 ✅ — GalleryResource (+PhotosRelationManager), PageResource,
  EventResource (через EventService).
- E6 ✅ — EventTypeResource (admin-only gate), RankResource,
  DashboardWidgetResource, ActivityLogResource (read-only, admin-only).
- E8.1 ✅ — MFA opt-in: миграция 000023, `AppAuthentication` в панели,
  `EnsureAdminMultiFactorAuthentication`.
- E8.2 ✅ — `EnsureAdminIpAllowed` (пустой список = allow-all).
- E8.3 ✅ — `LogsAdminActivity` + `AdminActivityLogger` + провайдер
  (`RecordCreated`/`RecordUpdated`, DeleteAction/DeleteBulkAction).
- E8.4 ✅ — `ReAuthenticateAction` для необратимых действий.
- E8.5 ✅ — Request workflow: restore (только статус), re-auth hard delete
  без каскада, фильтр «Запросы».
- Fix ✅ — `email_verified_at` добавлен в `#[Fillable]` `User`.
- Fix ✅ — R4 закрыт: смена ролей логируется как `user.roles_changed`
  (`EditUser` before/after save; тесты `UserRoleAuditTest`).
- E9 ✅ — acceptance gap-fill (15 тестов) + `docs/acceptance/stage-8.md`.
- Все отклонения R1–R7 закрыты; Stage 8 — ACCEPTED.

## Stage 9 — детализация

- E9.0 ✅ — ADR-004 (backup / restore / rollback) + STAGE-9-CONTRACT.md
  + CHANGELOG.md (минимальный) + sync docs.
- E9.1 ✅ — backup core: `BackupManifest`, `DatabaseDumper`,
  `FilesArchiver`, `BackupStorage`/`LocalBackupStorage`, `BackupLock`,
  `BackupService`, `config/backup.php`, `storage/backups/`.
- E9.2 ✅ — CLI `app:backup` + `app:backup-cleanup` (каркас) + Makefile
  (`make backup`) + `default-mysql-client` в dev Dockerfile.
- Amendment ADR-004 §3.1 — `lang/` включён в Scope B.
- E9.3–E9.8 — pending (E9.3: retention + safety).

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

См. docs/acceptance/stage-6.md §5 и docs/acceptance/stage-8.md §4.

## Порядок дальнейшей работы

1. Stage 9 — Backup / restore / rollback (Step 1: архитектура), по
   §30 context.md и `docs/ai/STAGE-8-CONTRACT.md` §12 (перенесено).
2. Stages 10–15 — по §30 context.md.
