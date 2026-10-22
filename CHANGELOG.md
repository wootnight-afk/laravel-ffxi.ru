# Changelog

Все значимые изменения проекта. Формат — по мотивам
[Keep a Changelog](https://keepachangelog.com/ru/1.1.0/), версии — SemVer
(context.md §25). Файл ведётся вручную.

## [Unreleased]

### Frontend — брендинг (логотипы в шапке/hero + favicon)

- **Логотип в навбаре:** `public/img/bx_default_logo.gif` в общем layout
  (`resources/views/layouts/app.blade.php`) вместо текстовой заглушки — на всех
  публичных страницах. Контейнер `.logo-wrap` + тематическая рамка + оверлей на
  тёмной теме (`[data-theme="dark"]`, токены палитры), footprint 40px (шапка не
  выросла).
- **Логотип на главной (hero):** `public/img/2a.png` в
  `resources/views/home.blade.php` через `.logo-wrap` (`max-height: 80px`,
  `border-radius: 4px`) — тот же паттерн подложки.
- **Favicon:** `public/favicon.ico` (было 0 байт → 1150 байт, рабочий ICO);
  подключён в публичном layout через `asset('favicon.ico')` и в админ-панели
  Filament (`AdminPanelProvider` → `->favicon(asset('favicon.ico'))`). Покрывает
  публичный сайт и админку.
- **Open issue:** мелькание favicon phpMyAdmin в браузере — в dev-контуре причина
  вне проекта (phpMyAdmin не на origin сайта; одна декларация `<link rel="icon">`;
  нет preload/prefetch/service worker). Cache-busting не применялся.
- **Хвост:** две косметические правки в `resources/css/app.css` (`body > header` →
  `body>header`, пустая строка после `@media (max-width: 767px) {`) — не по
  задаче, семантически нейтральны; включены как есть. Откатить отдельной задачей.
- Не добавлялось: apple-touch-icon, размеры 32×32 и пр., service worker, правки
  nginx.

### E10 — Unified MFA (docs finalized, implementation NOT STARTED)

- `ADR-009-unified-mfa.md` (Proposed): unified site-wide MFA, глобальный gate
  `mfa_global_enabled` (default true), единый challenge `/mfa/challenge`,
  escape-hatches `/cabinet/security` и `/admin/settings`, verification до logout,
  Filament `AppAuthentication` — storage/compat, R5-amendment.
- `E10-MFA-CONTRACT.md`: пофазный план E10.1–E10.8 + тестовая матрица T1–T24.
- Reconciliation: `STAGE-8-CONTRACT.md` §1 R5 (amendment), `frontend-spec.md`,
  `context.md` §13, `docs/acceptance/stage-8.md` (историческое примечание).
- E10.1 (2026-10-08) — закрытие аудита E10-DOCS D1–D8: `mfa.manage`
  (admin-only, MFA reset) в `frontend-spec.md` §3.3/§7.6; конкретная
  enforcement-матрица ADR-009 §2.3; TOTP/QR-стек без `bacon`
  (`pragmarx/google2fa-qrcode` + `chillerlan/php-qrcode`); устранение Filament
  profile MFA UI в E10.6; зафиксированы audit-API и расхождение `AuditLogger`;
  синхронизированы `PROJECT-STATUS.md` / `MASTER-TASK.md` / `KODA.md`.
- Code/DB/composer/migrations не изменялись.

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

### Access control — guest access denial (ADR-010)

- `SectionAccess`: закрытая для гостя секция Матрицы → redirect на `/login`
  вместо 404 для GET HTML-запросов; `Redirector::guest()` сохраняет исходный
  URL в `intended`, поэтому после входа открывается запрошенная страница при
  наличии `section.{key}.view` (frontend-spec.md §3.6/§9.17).
- HEAD: redirect на `/login`, однако сохранение исходного URL не гарантируется
  (поведение `Redirector::guest()`; нестандартная логика не добавлялась).
- JSON-клиенты и не-GET запросы, дошедшие до middleware как гость → 403;
  адрес изменяющего запроса в `intended` не сохраняется.
- Авторизованный без `section.{key}.view` → 403 (без изменений).
- 404 для отсутствующих объектов и скрытого приватностью контента не менялись.
- Docs: `ADR-010`, `frontend-spec.md` 1.4, `context.md` 4.0.4.

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
