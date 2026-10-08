# Project Status — laravel-ffxi.ru

## HEAD
`b0c68d9` на ветке `main` (E10.3 — MFA setup `/cabinet/security`). E10.1
(`68f5eae`), E10.2 (`c65e1f7`), E10.3 (`b0c68d9`) запушены; E10.4 — в рабочем
дереве, без commit. Stage 9 закрыт `bb3bbf7`.

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
| 9 | Backup/restore | DONE / ACCEPTED | docs/adr/ADR-004-backup-restore.md, docs/ai/STAGE-9-CONTRACT.md, docs/acceptance/stage-9.md |
| 10 | Tests (§9) | NOT STARTED — ожидает отдельного решения после локальной проверки проекта | — |
| 11 | CI | NOT STARTED | — |
| 12 | Timeweb staging | NOT STARTED | — |
| 13 | Deployment | NOT STARTED | — |
| 14 | Production | NOT STARTED | — |
| 15 | Update UI | NOT STARTED | — |

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
- E9.3 ✅ — retention GFS 7/4/12 (`BackupRetention`), реальный
  `app:backup-cleanup` (под backup-lock), free-space safety
  (`FilesArchiver::estimateSize` + `min_free_space_pct`), settings-ключи
  `backup_retention_{daily,weekly,monthly}` и `min_free_space_pct`.
- E9.4 ✅ — Filament `BackupPage` (admin-only, `/admin/backups`):
  таблица backup'ов (array-records), создание (radio mode/scope + Scope B
  warning), просмотр/download manifest, удаление, retention settings;
  audit `backup.created`/`backup.deleted` без IP (contract §12). Restore —
  только CLI (R4); restore-request flow — E9.5 (§2).
- E9.5 ✅ — restore: миграция/модель `restore_requests` (UUID, HMAC-SHA256,
  expiry 30 мин), `RestoreRequestService`, `RestoreService` (read-only
  `check` + `apply`: lock, auto-backup, maintenance, DB→files, smoke-test,
  audit), `app:rollback LIST/CHECK/APPLY`, `app:restore-test` (каркас),
  `make rollback`, restore-request flow в `BackupPage` (re-auth + MFA
  панели, admin-only). Audit `restore.requested/applied/failed/rejected`
  с IP (security-события). HTTP restore отсутствует (R4).
- E9.6 ✅ — Scheduler (`bootstrap/app.php`): `04:00` DB-only backup
  (`app:backup --mode=db --triggered-by=cron`) + `04:30` retention cleanup
  (`app:backup-cleanup`), оба `withoutOverlapping()`; feature-тест
  `tests/Feature/Scheduler/BackupScheduleTest.php`.
- E9.7 ✅ — `app:restore-test` (реализация) + `make restore-test`:
  `RestoreTestService` восстанавливает backup в изолированную dev-БД
  `laravel_ffxi_restore_test` (integrity-проверка `hash_db` → DROP/CREATE →
  dump → smoke-test → DROP в `finally`), не затрагивая рабочую БД;
  guard на совпадение с рабочей БД; `config/backup.php` блок `restore_test`.
- E9.8 ✅ — приёмка: `docs/acceptance/stage-9.md`; закрыты пробелы
  audit-событие `backup.cleanup` (§12/§13.22) и тест провала совместимости
  (§13.15); `RestoreTestService` добавлен в карту компонентов контракта §3.1;
  синхронизированы статусные документы.

## Stage 9 — открытые gaps

- **Q6** — внешняя копия backup (S3/restic/вручную) не реализована
  (out of scope Stage 9, ADR-004 §8.2). Точка расширения — `BackupStorage`.
- Рассинхронизация migrations при restore старого dump — известный риск
  (§6.4 п.4), вне scope Stage 9.

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

## MFA — ADR-009 / E10 (E10.1–E10.4; implementation in progress)

- `docs/adr/ADR-009-unified-mfa.md` — **Proposed / awaiting implementation**:
  unified site-wide MFA; глобальный gate `mfa_global_enabled` (default true);
  единый challenge `/mfa/challenge`; escape-hatches `/cabinet/security`,
  `/admin/settings`; verification до logout; storage/compat Filament; R5-amendment;
  enforcement-матрица по маршрутам (§2.3); permission `mfa.manage` (admin-only,
  MFA reset); TOTP/QR-стек без `bacon`.
- `docs/ai/E10-MFA-CONTRACT.md` — пофазный план E10.1–E10.8 + тестовая матрица (T1–T24).
- **Прогресс реализации:**
  - E10.2 ✅ — escape-hatch `/admin/settings` (`c65e1f7`).
  - E10.3 ✅ — MFA setup `/cabinet/security` (`b0c68d9`; self-service setup/confirm/
    disable/regenerate/cancel; Blade + POST; только свой аккаунт; re-auth для
    disable и regenerate; audit `mfa.enabled`/`mfa.disabled`).
  - E10.4 ✅ — unified challenge `/mfa/challenge` + middleware `mfa.required`
    (`RequireMfa`): enforcement для `/players` и admin-панели (persistent, покрывает
    Livewire); session-ключи `mfa_verified_user_id`/`mfa_verified_at` (binding к user,
    T24); global gate; escape-hatches; editor `/admin/profile` skip; rate limit 5/мин;
    audit `mfa.challenge_success`/`mfa.challenge_failure`. Легаси-middleware
    `EnsureAdminMultiFactorAuthentication` сохранён, но shadowed (удаление — E10.6).
- **Tests:** 739 passed (2360 assertions) после E10.4.
- E10.1 (2026-10-08) закрыл пункты аудита E10-DOCS D1–D8 в документации:
  `mfa.manage` в `frontend-spec.md` §3.3/§7.6; синхронизация статусных документов;
  конкретная enforcement-матрица; TOTP/QR без composer-change; устранение Filament
  profile MFA UI в E10.6; audit-API и расхождение `AuditLogger` зафиксированы.
- **Enforcement-матрица (D5) закрыта:** `/players` — единая защищаемая область
  (`players.dashboard`, `players.directory`, `players.show`); editor
  `GET /admin/profile` вне матрицы; Filament setup-route нейтрализуется в E10.6.
- **Расхождение `AuditLogger`** (security-specific IP: `STAGE-8-CONTRACT` §3.3 vs
  текущий default `recordIp = true`) — отдельный тех-долг Stage 8; закрыть до E10.7,
  если потребуется изменение.
- **Runtime state:** в текущей БД `admin_2fa_required = true` (запись от
  2026-10-03) при архитектурном default `false`; `mfa_global_enabled` отсутствует.
  Автоматически не изменяется.
- Порядок: E10.1 → E10.2 → E10.3 → E10.4 → E10.5 → E10.6 → E10.7 → E10.8.

## Порядок дальнейшей работы

1. Stage 9 — Backup / restore / rollback — DONE / ACCEPTED
   (`docs/acceptance/stage-9.md`).
2. E10 — Unified MFA (ADR-009) — E10.1 + E10.2 + E10.3 запушены (`68f5eae`,
   `c65e1f7`, `b0c68d9`); E10.4 (unified challenge + `RequireMfa`) — в рабочем
   дереве, без commit; следующий шаг — E10.5 (Admin Settings → Security).
3. Stage 10 — Tests (§9) — NOT STARTED, ожидает отдельного решения после
   локальной проверки проекта владельцем.
4. Stages 11–15 — по §30 context.md (CI, staging, deployment, production,
   update UI).
