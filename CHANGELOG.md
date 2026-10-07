# Changelog

Все значимые изменения проекта. Формат — по мотивам
[Keep a Changelog](https://keepachangelog.com/ru/1.1.0/), версии — SemVer
(context.md §25). Файл ведётся вручную.

## [Unreleased]

### Stage 9 — Backup / Restore / Rollback (DONE / ACCEPTED)

- E9.0 — ADR-004 (backup / restore / rollback) + `STAGE-9-CONTRACT.md` +
  минимальный `CHANGELOG.md`.
- E9.1 — backup core: `BackupManifest`, `DatabaseDumper`, `FilesArchiver`,
  `BackupStorage`/`LocalBackupStorage`, `BackupLock`, `BackupService`,
  `config/backup.php`.
- E9.2 — CLI `app:backup` + `make backup`; `default-mysql-client` в dev.
- E9.3 — retention GFS `7/4/12` (`BackupRetention`), `app:backup-cleanup`,
  free-space safety.
- E9.4 — admin `BackupPage` (create/list/manifest/download/delete +
  retention settings).
- E9.5 — restore: `restore_requests` (HMAC-SHA256, expiry 30 мин),
  `RestoreService`, `app:rollback LIST/CHECK/APPLY`, restore-request flow.
- E9.6 — scheduler: `04:00` DB-only backup + `04:30` retention cleanup
  (`withoutOverlapping()`).
- E9.7 — `app:restore-test` + `make restore-test` (изолированная dev-БД
  `laravel_ffxi_restore_test`).
- E9.8 — приёмка (`docs/acceptance/stage-9.md`); audit-событие
  `backup.cleanup`; тест провала совместимости.
- **Открытый gap:** внешняя копия backup (Q6) не реализована (out of scope).

## [Stage 8] — 2026-10-06 — Filament (ACCEPTED)

- Полная админ-панель Filament: 12 Resources + `PermissionsMatrixPage`
  и `SettingsPage`.
- MFA (opt-in), IP allowlist (opt-out), аудит административных действий
  (включая `user.roles_changed`), re-auth для необратимых действий.
- Workflow запросов пользователей (restore, hard delete без каскада).
- Приёмка: `docs/acceptance/stage-8.md`.

## [Stage 7] — 2026-10-04 — Dashboard (ACCEPTED)

- Чат, события, activity feed, online users, 🔔, data-driven widgets.
- Scheduler: напоминания о событиях, retention activities.
- Приёмка: `docs/acceptance/stage-7.md`.

## [Stage 6] — 2026-10-04 — Профили и кабинет (ACCEPTED)

- Страницы игроков, каталог, кабинет, компоненты identity/rank/avatar.
- Приёмка: `docs/acceptance/stage-6.md`.

## [Stages 1–5]

- ADR-001 (стек), Docker dev + skeleton, auth/роли/фундамент, content
  engine (новости, страницы, комментарии, sanitizer), галерея
  (`ImageProcessor`, `ProcessPhotoJob`).

## [Stage 0]

- Аудит Timeweb — pending (владелец).
