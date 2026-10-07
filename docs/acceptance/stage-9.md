# Stage 9 — Backup / Restore / Rollback: приёмочный отчёт

| Поле | Значение |
|---|---|
| Дата | 2026-10-07 |
| HEAD | 5fdf413 (E9.7) + E9.8 |
| Ветка | main |
| Статус | ACCEPTED (ожидает ручной проверки владельцем) |
| Контракт | `docs/ai/STAGE-9-CONTRACT.md` |
| ADR | `docs/adr/ADR-004-backup-restore.md` |

---

## 1. Что реализовано (E9.1–E9.7)

### E9.0 — ADR-004 + контракт (commit `d536cc9`)
- `docs/adr/ADR-004-backup-restore.md` (R1–R12), `docs/ai/STAGE-9-CONTRACT.md`,
  минимальный `CHANGELOG.md`.

### E9.1 — Backup core (commit `08c8e1f`)
- `BackupManifest` (DTO + структурная валидация), `DatabaseDumper`
  (`mysqldump`, `--defaults-extra-file` 0600, gzip), `FilesArchiver`
  (`tar`/`gzip`, Scope A/B), `BackupStorage`/`LocalBackupStorage`,
  `BackupLock` (flock + mkdir-fallback), `BackupService`,
  `config/backup.php`, `storage/backups/` вне Git.

### E9.2 — CLI + Makefile (commits `74249c2`, `a85cab7`)
- `app:backup --mode --scope --triggered-by`; `make backup`;
  `default-mysql-client` в dev Dockerfile; dev-only TLS-конфиг MySQL client;
  `.gitignore` для backup-lock.

### E9.3 — Retention + safety (commit `26c2207`)
- `BackupRetention` GFS `7/4/12`, реальный `app:backup-cleanup` под
  backup-lock, free-space safety (`estimateSize` + `min_free_space_pct`),
  settings-ключи `backup_retention_{daily,weekly,monthly}`,
  `min_free_space_pct`.

### E9.4 — BackupPage (commit `3c9ca0f`)
- Filament `BackupPage` (admin-only, `/admin/backups`): список (array-records),
  создание (mode + scope + Scope B warning), просмотр/download manifest,
  удаление, retention-настройки; audit `backup.created`/`backup.deleted`
  без IP (contract §12). Restore — только CLI.

### E9.5 — Restore requests + rollback (commit `efb4a6a`)
- Миграция/модель `restore_requests` (UUID, HMAC-SHA256, expiry 30 мин),
  `RestoreRequestService`, `RestoreService` (read-only `check` + `apply`:
  lock, auto-backup, maintenance, DB→files, smoke-test, audit),
  `app:rollback LIST/CHECK/APPLY`, restore-request flow в `BackupPage`
  (re-auth + MFA, admin-only). Audit `restore.requested/applied/failed/rejected`
  с IP. HTTP restore отсутствует (R4).

### E9.6 — Scheduler (commit `b8b29f3`)
- `bootstrap/app.php` (`withSchedule`): `04:00` DB-only backup
  (`app:backup --mode=db --triggered-by=cron`) + `04:30` retention cleanup
  (`app:backup-cleanup`), оба `withoutOverlapping()`.

### E9.7 — Restore-test (commit `5fdf413`)
- `RestoreTestService`: изолированная БД (`laravel_ffxi_restore_test`),
  integrity-проверка `hash_db` до касания БД, `DROP/CREATE`, загрузка dump,
  smoke-test (таблицы, `migrations`, `db_schema_version`), `DROP` в `finally`.
- `app:restore-test <id>` (тонкая команда) + `make restore-test <id>`.
- `config/backup.php` блок `restore_test` (env-driven:
  `BACKUP_RESTORE_TEST_DATABASE/USERNAME/PASSWORD`); `.env.example` обновлён.
- Guard: отказ, если изолированная БД совпадает с рабочей.

### E9.8 — Приёмка и синхронизация (этот отчёт)
- Закрыты два обязательных пробела контракта (см. §4):
  audit-событие `backup.cleanup` и тест провала совместимости (§13.15).
- `RestoreTestService` добавлен в карту компонентов контракта §3.1.
- Синхронизированы `PROJECT-STATUS.md`, `KODA.md`, `CHANGELOG.md`.

---

## 2. Соответствие R1–R12 (ADR-004)

| Решение | Реализация | Тесты | Статус |
|---|---|---|---|
| **R1** — единый application-level `BackupService` без Docker/Redis/Supervisor | `BackupService` + `BackupStorage` abstraction | `BackupServiceTest` | ✅ |
| **R2** — режимы `full`/`site_only`/`db_only`; DB-only ⇒ `scope=null`, `is_complete=false` | `BackupService::normaliseMode/Scope`, `BackupManifest::validate` | `BackupServiceTest`, `BackupManifestTest` | ✅ |
| **R3** — `BackupPage` в полном объёме | `App\Filament\Pages\BackupPage` | `BackupPageTest`, `BackupRestoreRequestTest` | ✅ |
| **R4** — restore только через CLI | `BackupPage::restoreRequest` создаёт только `RestoreRequest`; `RestoreService` вызывается только из CLI | `BackupRestoreRequestTest`, `RollbackCommandTest` | ✅ |
| **R5** — `restore_requests` + HMAC-SHA256 + expiry 30 мин | `RestoreRequestService`, миграция `restore_requests` | `RestoreRequestServiceTest` | ✅ |
| **R6** — retention `7/4/12` + free-space safety | `BackupRetention` | `BackupRetentionTest`, `BackupSpaceSafetyTest` | ✅ |
| **R7** — scheduler: daily DB-only + cleanup; full — вручную | `bootstrap/app.php` | `BackupScheduleTest` | ✅ |
| **R8** — `default-mysql-client` в dev Dockerfile | `docker/php/Dockerfile` | `DatabaseDumperTest` | ✅ |
| **R9** — HMAC-подпись manifest не требуется | не реализована | — | ✅ (осознанно) |
| **R10** — `CHANGELOG.md` | `CHANGELOG.md`, `app_changelog_hash` | `BackupServiceTest` | ✅ |
| **R11** — audit через существующий `AuditLogger` | `AuditLogger` | `BackupPageTest`, `BackupRestoreRequestTest`, `RestoreServiceTest`, `RollbackCommandTest`, `BackupCleanupCommandTest` | ✅ |
| **R12** — Scope B — точный состав ADR-004 §3 | `FilesArchiver` | `FilesArchiverTest` | ✅ |

---

## 3. Покрытие сценариев контракта §13

| # | Сценарий | Покрытие | Статус |
|---|---|---|---|
| 1 | Manifest: обязательные поля; `db_only`⇒`scope=null`/`is_complete=false`; `full`/`site_only`⇒`is_complete=true` | `Unit/Backup/BackupManifestTest` | ✅ |
| 2 | Валидация режим/scope (без scope — ошибка; db_only + scope → null) | `BackupServiceTest`, `BackupCommandTest` | ✅ |
| 3 | `BackupService`: full / site_only / db_only; артефакты, manifest, хэши | `BackupServiceTest` | ✅ |
| 4 | Scope A: архив содержит `storage/app/` | `FilesArchiverTest` | ✅ |
| 5 | Scope B: состав ADR-004 §3; исключает `vendor/`, `node_modules/`, `storage/backups/` | `FilesArchiverTest` | ✅ |
| 6 | `LocalBackupStorage`: put/get/exists/delete/size/list | `LocalBackupStorageTest` | ✅ |
| 7 | `BackupLock`: повторный захват — отказ; освобождение | `Unit/Backup/BackupLockTest` | ✅ |
| 8 | Retention `7/4/12`: отбор кандидатов; защищённые не удаляются | `BackupRetentionTest`, `BackupCleanupCommandTest` | ✅ |
| 9 | Free-space safety: недостаток места — отказ без создания backup | `BackupSpaceSafetyTest`, `BackupRetentionTest` | ✅ |
| 10 | `app:backup` CLI: режимы/scope/`--triggered-by` | `BackupCommandTest` | ✅ |
| 11 | `app:backup-cleanup` CLI: удаляет только незащищённые | `BackupCleanupCommandTest` | ✅ |
| 12 | `restore_requests`: создание, HMAC, expiry 30 мин | `RestoreRequestServiceTest` | ✅ |
| 13 | `app:rollback CHECK`: валидный/истёкший/подделанный token | `RollbackCommandTest` | ✅ |
| 14 | `app:rollback APPLY`: полный flow с auto-backup и maintenance | `RestoreServiceTest`, `RollbackCommandTest` | ✅ |
| 15 | Провал совместимости — отказ без изменений + audit | `RestoreServiceTest` (E9.8: добавлен тест) | ✅ |
| 16 | HTTP не выполняет restore (нет прямого вызова) | `BackupRestoreRequestTest` (HTTP создаёт только request) + ревью кода: `RestoreService` не вызывается из HTTP | ✅ |
| 17 | `BackupPage`: доступ только admin; editor/user — отказ | `BackupPageTest`, `BackupRestoreRequestTest` | ✅ |
| 18 | `BackupPage`: список/создание/download/delete/manifest | `BackupPageTest` | ✅ |
| 19 | Warning для Scope B | `BackupPageTest`, `BackupCommandTest` | ✅ |
| 20 | Scheduler: задачи зарегистрированы с `withoutOverlapping()` | `BackupScheduleTest` | ✅ |
| 21 | `app:restore-test`: изолированная БД, не затрагивает рабочую | `RestoreTestCommandTest` + E2E (§6) | ✅ |
| 22 | Audit-события создаются; IP только для security-событий | `BackupPageTest`, `BackupRestoreRequestTest`, `RestoreServiceTest`, `RollbackCommandTest`, `BackupCleanupCommandTest` | ✅ |
| 23 | Регрессия: Stage 1–8 не сломаны | полный Pest (см. §7) | ✅ |

Сценарий 16 — архитектурный инвариант (HTTP не вызывает `RestoreService`).
Он подтверждён ревью кода (`BackupPage` создаёт только `RestoreRequest`;
единственный вызов `RestoreService::apply` — из `RollbackCommand`) и
поведенческим тестом `BackupRestoreRequestTest` (HTTP оставляет `status=pending`
и не выполняет restore). Отдельный «негативный» тест не добавлялся, чтобы не
дублировать уже проверенное поведение.

---

## 4. Пробелы, закрытые при приёмке (E9.8)

| Пробел | Требование | Исправление |
|---|---|---|
| Audit-событие `backup.cleanup` не создавалось | §12 (список событий), §13.22 | `BackupCleanupCommand` пишет `backup.cleanup` (`recordIp: false`); тест в `BackupCleanupCommandTest` |
| Провал совместимости не покрыт тестом | §13.15 | Тест «refuses to apply an incompatible backup without changes and audits the failure» в `RestoreServiceTest` |

Оба исправления — точечные, в рамках контракта; архитектура E9.1–E9.7 не
изменялась.

---

## 5. Известные gaps и открытые вопросы

| Gap | Причина / статус |
|---|---|
| **Q6 — внешняя копия backup (S3/restic/ручная)** | **Открыт.** Требование §17 context.md «обязательная копия вне Timeweb» в Stage 9 не реализовано: внешнее хранилище отложено (ADR-004 §8.2, contract §0 «Out of scope»). Точка расширения — интерфейс `BackupStorage`. НЕ закрывать без фактической реализации и проверки. |
| Рассинхронизация migrations при restore старого DB dump | Известный риск (§6.4 п.4). `db_schema_version` проверяется на известность текущему коду, но автоматическая миграция после restore не выполняется. Вне scope E9.8. |
| HMAC-подпись manifest | Не реализована осознанно (R9). |
| Шифрование dump | Не реализовано (out of scope, contract §0). |
| `app_version` на production без `.git` | Может быть `null` (ADR-004 §8.2 п.5); deploy-файл версии — Stage 13. |
| Trusted proxies / реальный IP за прокси | Stage 12. |

---

## 6. E2E-проверка (dev-стек)

Проведена на работающем Docker-стеке (`make restore-test`):

| Backup | Тип | Результат |
|---|---|---|
| `03bc47cb-…` | `db_only` | tables restored: 31, migrations: 27, smoke: OK |
| `6950652c-…` | `full` (`storage_app`) | tables restored: 32, migrations: 28, smoke: OK |

Проверено:
- изолированная БД `laravel_ffxi_restore_test` создаётся и **удаляется** после
  прогона (в `information_schema.schemata` отсутствует);
- рабочая dev-БД не изменяется (`users`: 3 → 3; число таблиц не менялось);
- `make restore-test <unknown-id>` корректно завершается ошибкой;
- `make rollback LIST` продолжает работать.

Также ранее подтверждено (E9.2/E9.3): реальный `app:backup` создаёт
`db_only`/`full` backup на dev-стеке с настоящим `mysqldump`.

---

## 7. Definition of Done §14 контракта

- [x] `BackupService` работает в режимах `full`/`site_only`/`db_only` и обоих
      scopes, без инфраструктурных зависимостей.
- [x] Manifest содержит `type`, `scope`, `is_complete` и поля §17.
- [x] `storage/backups/` вне Git; `.gitignore` обновлён.
- [x] CLI `app:backup` и `make backup` работают.
- [x] Retention `7/4/12` + free-space safety реализованы.
- [x] `BackupPage` (admin-only): создание/список/manifest/download/delete/
      restore-request/warning Scope B.
- [x] `restore_requests` + HMAC-SHA256 + expiry 30 мин; restore только через
      CLI; HTTP restore отсутствует.
- [x] Scheduler: daily DB-only backup + cleanup, `withoutOverlapping()`.
- [x] `app:restore-test` в изолированной БД.
- [x] Audit через существующий `AuditLogger`; `backup.cleanup` добавлен (E9.8).
- [x] Pest, Pint, Larastan, `git diff --check` проходят.
- [x] `docs/acceptance/stage-9.md` отражает результаты и gaps.
- [ ] Ручная проверка владельцем — предстоит после коммита отчёта.

---

## 8. Результаты проверок (после E9.8)

- **Pest:** 678 passed (2146 assertions).
- **Larastan:** `[OK] No errors`.
- **Pint:** PASS (359 files).
- **`php -l`** изменённых PHP-файлов: без ошибок.
- **`git diff --check`:** exit 0.
- **E2E:** `make restore-test` — см. §6.

---

## 9. Ссылки

- `docs/adr/ADR-004-backup-restore.md`.
- `docs/ai/STAGE-9-CONTRACT.md` §3.1, §7, §10, §11, §12, §13, §14.
- `docs/ai/context.md` §7, §11, §17, §22, §23, §24, §27, §30, §32, §38 (Q6).
- `docs/ai/frontend-spec.md` §7.5.
- `docs/acceptance/stage-8.md`.
