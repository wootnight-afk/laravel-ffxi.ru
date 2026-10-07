# Stage 9 — Backup / Restore / Rollback. Архитектурный контракт

> Статус: решения владельца R1–R12 зафиксированы для проектирования Stage 9.
> Этот документ не выполняет и не заменяет runtime-изменения. При расхождении
> с кодом существующее поведение не считается автоматически утверждённым
> контрактом.

## §0. Контекст и границы

Stage 9 добавляет подсистему резервного копирования, восстановления и
rollback поверх уже реализованного приложения (Stages 1–8 приняты).

**In scope:**

- Единый application-level `BackupService` (dev + production).
- Режимы `full`, `site_only`, `db_only`; scopes `storage_app` и `whole_site`.
- Manifest с обязательными полями `type`, `scope`, `is_complete` и полями §17.
- CLI `app:backup`, `app:backup-cleanup`, `app:rollback`, `app:restore-test`.
- Filament `BackupPage` (создание, список, manifest, download, delete,
  restore-request, warning для Scope B).
- Таблица `restore_requests`, HMAC-SHA256, expiry 30 минут.
- Retention `7/4/12` + safety по свободному месту.
- Scheduler: daily DB-only backup + retention cleanup.
- Restore-test в изолированной dev-БД.

**Out of scope:**

- Внешнее хранилище backup (S3/MinIO/restic) — post-production, через
  abstraction `BackupStorage`. Q6 остаётся открытым.
- HMAC-подпись manifest.
- Шифрование dump.
- Изменение ролей, permissions, Policy abilities Stage 8.
- Изменение runtime Stage 1–8 за пределами интеграционных точек,
  перечисленных в этом контракте.
- Автоматический ежедневный full backup.
- Size-quota backup-хранилища.

**Существующая база:** `SettingsRepository`, `AuditLogger`,
`AdminActivityLogger`, `AdminPanelProvider`, `ReAuthenticateAction`,
механизм MFA панели, `bootstrap/app.php` (`withSchedule`),
`docker/php/Dockerfile`. Их наличие не означает наличия backup-логики.

---

## §1. Решения владельца (R1–R12)

Решения R1–R12 полностью изложены в
[`ADR-004`](../adr/ADR-004-backup-restore.md) §2. Контракт на них
ссылается и не дублирует обоснование:

| Решение | Содержание |
|---|---|
| R1 | Единый `BackupService`, не зависит от Docker/Redis/Supervisor/workers |
| R2 | Режимы `full` / `site_only` / `db_only`; DB-only → `scope=null`, `is_complete=false` |
| R3 | `BackupPage` в полном объёме Stage 9 |
| R4 | Восстановление только через CLI; HTTP restore запрещён |
| R5 | `restore_requests`: отдельная таблица, HMAC-SHA256, `APP_KEY`, expiry 30 мин |
| R6 | Retention `7/4/12` + free-space safety; без size-quota |
| R7 | Scheduler: daily DB-only + cleanup; full — вручную/перед deploy |
| R8 | `default-mysql-client` в dev Dockerfile |
| R9 | HMAC-подпись manifest не требуется |
| R10 | `CHANGELOG.md` создаётся (E9.0) |
| R11 | Audit через существующий `AuditLogger`; IP — только для security-событий |
| R12 | Scope B — только состав из ADR-004 §3 |

---

## §2. Порядок подэтапов

Порядок строго: **E9.1 → E9.2 → E9.3 → E9.4 → E9.5 → E9.6 → E9.7 → E9.8**.

| Подэтап | Содержание | Зависит от |
|---|---|---|
| **E9.1** | Backup core: `BackupManifest`, `DatabaseDumper`, `FilesArchiver`, `BackupStorage`/`LocalBackupStorage`, `BackupService`, `BackupLock`, `config/backup.php`, `storage/backups/` + `.gitignore` | ADR-004 |
| **E9.2** | CLI `app:backup` + `app:backup-cleanup` (каркас) + `Makefile` (`make backup`) + `default-mysql-client` в Dockerfile | E9.1 |
| **E9.3** | `BackupRetention` + реализация `app:backup-cleanup` + settings retention/safety | E9.1, E9.2 |
| **E9.4** | `BackupPage` (Filament, admin-only) | E9.1, E9.3 |
| **E9.5** | `restore_requests` (миграция + модель), `RestoreRequestService`, `RestoreService`, `app:rollback LIST/CHECK/APPLY`, `app:restore-test` (каркас), restore-request flow в BackupPage | E9.4 |
| **E9.6** | Scheduler: `04:00` DB-only backup, `04:30` cleanup, `withoutOverlapping()` | E9.2, E9.3 |
| **E9.7** | `app:restore-test` (реализация) + `make restore-test` (изолированная dev-БД) | E9.5 |
| **E9.8** | Тесты + `docs/acceptance/stage-9.md` + синхронизация статуса | все |

Каждый Ei — отдельная законченная единица: targeted tests → полный
quality gate → diff check → один commit → отчёт → STOP. Следующий Ei —
только после отдельного OK владельца.

---

## §3. Архитектура

### 3.1. Карта компонентов

```
App\Contracts\BackupStorage               (interface)
  └── App\Services\Backup\LocalBackupStorage

App\Services\Backup\BackupManifest        (DTO)
App\Services\Backup\DatabaseDumper        (mysqldump)
App\Services\Backup\FilesArchiver         (tar.gz)
App\Services\Backup\BackupLock            (flock → mkdir fallback)
App\Services\Backup\BackupService         (orchestrator)
App\Services\Backup\BackupRetention       (7/4/12 + safety)
App\Services\Backup\RestoreRequestService (HMAC request)
App\Services\Backup\RestoreService        (restore flow)
App\Services\Backup\RestoreTestService    (isolated-db restore-test)

App\Console\Commands\BackupCommand
App\Console\Commands\BackupCleanupCommand
App\Console\Commands\RollbackCommand
App\Console\Commands\RestoreTestCommand

App\Models\RestoreRequest
database/migrations/*_create_restore_requests_table.php

App\Filament\Pages\BackupPage

config/backup.php
```

### 3.2. `BackupService`

Оркестратор одного backup:

1. Сгенерировать `backup_id` (UUID).
2. Собрать метаданные (`php_version`, версии из `composer.lock`,
   `composer_lock_hash`, `package_lock_hash`, `db_schema_version`,
   `db_migrations`, `extensions`, `app_version`, `app_changelog_hash`).
3. Валидировать режим/scope (см. §5).
4. Safety-проверка свободного места (§9.3).
5. Если режим включает DB — `DatabaseDumper` → dump → `hash_db`.
6. Если режим включает файлы — `FilesArchiver` → архив → `hash_files`.
7. Сформировать `BackupManifest`.
8. Сохранить артефакты и manifest через `BackupStorage`.
9. Вернуть `BackupManifest`.

`BackupService` не знает о конкретном хранилище — работает через
`BackupStorage`. Внешнее хранилище подключается позже без изменений
сервиса.

### 3.3. `DatabaseDumper`

- Дамп через `mysqldump` (бинарник из `config/backup.php`), запуск через
  `Illuminate\Process` (`Symfony Process`).
- Учётные данные БД передаются через временный
  `--defaults-extra-file` (mode `0600`), который удаляется после
  выполнения; пароль не попадает в список процессов.
- Параметры: `--single-transaction --quick --skip-lock-tables --routines
  --triggers --no-tablespaces`.
- Результат сжимается `gzip` (расширение `.dump.gz`).
- Возвращает путь к dump и `hash_db`.
- При отсутствии `mysqldump` — понятная ошибка с инструкцией (dev:
  `default-mysql-client`; production: проверить §7).

### 3.4. `FilesArchiver`

- Архив через `tar` + `gzip` (расширение `.files.tar.gz`), запуск через
  `Illuminate\Process`.
- Scope A (`storage_app`): архив `storage/app/`.
- Scope B (`whole_site`): архив точного состава из ADR-004 §3 (список
  include + exclude).
- Формирует список путей во временный list-файл, запускает
  `tar -czf <out> -C <project_root> -T <list> --exclude=...`.
- Возвращает путь к архиву и `hash_files`.
- При отсутствии `tar` — понятная ошибка.

### 3.5. `BackupStorage` / `LocalBackupStorage`

```php
interface BackupStorage
{
    public function put(string $relativePath, string $sourcePath): void;
    public function get(string $relativePath): string;   // local absolute path
    public function exists(string $relativePath): bool;
    public function delete(string $relativePath): void;
    public function size(string $relativePath): int;
    public function list(): array;                        // manifests
}
```

`LocalBackupStorage` хранит артефакты в `storage/backups/` (вне web
root). Каталог не попадает в Git (`.gitignore`).

Внешнее хранилище (out of scope) реализует тот же интерфейс.

### 3.6. `BackupLock`

- Файловая блокировка `storage/framework/backup.lock`.
- Основной механизм — `flock`; fallback при отсутствии `flock` —
  mkdir-блокировка (context.md §10).
- Используется backup и restore, чтобы исключить параллельные
  backup/restore и пересечение с deploy.
- Неблокирующий захват: при занятом lock операция завершается отказом
  без изменений.

### 3.7. `RestoreRequestService`

- Создаёт строку `restore_requests`.
- `token` = HMAC-SHA256 от `(request_id|backup_id|admin_user_id|expires_at)`
  с ключом `APP_KEY`.
- `expires_at` = `now() + 30 минут`.
- Проверяет token, срок, статус; помечает `consumed_at` при применении.

### 3.8. `RestoreService`

- Реализует restore flow §6: проверки, блокировка, auto-backup,
  maintenance, восстановление БД и файлов, `/up`, smoke-test, audit.
- Не вызывается из HTTP (R4).

### 3.9. `config/backup.php` и settings

Статические параметры — `config/backup.php` (env-driven):

```php
return [
    'disk' => env('BACKUP_DISK', 'local'),
    'path' => storage_path('backups'),
    'manifest_version' => 1,
    'binaries' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL', 'mysql'),
        'tar' => env('BACKUP_TAR', 'tar'),
    ],
    'lock_path' => storage_path('framework/backup.lock'),
    'external' => ['enabled' => false],
];
```

Runtime-параметры — `settings` (DB), редактируются через Settings:

| Ключ | Default |
|---|---|
| `backup_retention_daily` | `7` |
| `backup_retention_weekly` | `4` |
| `backup_retention_monthly` | `12` |
| `min_free_space_pct` | `5` |

Один runtime-параметр — один источник истины. Retention/safety — только
`settings`; пути/бинарники/версия формата — только `config`.

---

## §4. `BackupManifest`

DTO с полным набором полей из ADR-004 §4:

- обязательные Stage 9: `format_version`, `backup_id`, `type`, `scope`,
  `is_complete`;
- §17: `created_at`, `triggered_by`, `app_version`, `app_changelog_hash`,
  `php_version`, `laravel_version`, `filament_version`, `livewire_version`,
  `composer_lock_hash`, `package_lock_hash`, `db_schema_version`,
  `db_migrations`, `extensions`, `db_size_bytes`, `files`, `hash_db`,
  `hash_files`, `created_by_user_id`;
- дополнительные: `size_bytes`, `artifacts`.

Правила:

- `type=db_only` ⇒ `scope=null`, `is_complete=false`, `hash_files=null`,
  `files=[]`.
- `type=full`/`site_only` ⇒ `is_complete=true`, `scope` задан.
- Manifest сериализуется в `{backup_id}.manifest.json`.
- HMAC-подпись не добавляется (R9).

---

## §5. Режимы, scopes, именование, пути

### 5.1. Валидация режим/scope

| Режим | Scope | Результат |
|---|---|---|
| `full` | `storage_app` | DB + `storage/app/` |
| `full` | `whole_site` | DB + Scope B (ADR-004 §3) |
| `site_only` | `storage_app` | только `storage/app/` |
| `site_only` | `whole_site` | только Scope B |
| `db_only` | (не применяется) | только DB, `scope=null` |

- Для `full`/`site_only` scope обязателен.
- Для `db_only` scope не применяется и в manifest = `null`.
- Некорректная комбинация — ошибка без создания backup.

### 5.2. Именование и пути

```
storage/backups/{YYYY}/{MM}/{backup_id}.dump.gz
storage/backups/{YYYY}/{MM}/{backup_id}.files.tar.gz
storage/backups/{YYYY}/{MM}/{backup_id}.manifest.json
```

- `{YYYY}`/`{MM}` — из `created_at` (UTC).
- Каталог `storage/backups/` не в Git: строка `/storage/backups/` в
  корневом `.gitignore` + `storage/backups/.gitignore`.

### 5.3. Предупреждение Scope B

При `full`/`site_only` со scope `whole_site` BackupPage и CLI выводят
предупреждение: архив содержит `.env` и секреты приложения
(ADR-004 §3.4).

---

## §6. Restore flow

### 6.1. Схема

```
BackupPage → restore_request → re-auth / MFA → защищённый request
           → CLI → CHECK → APPLY
```

Фактическое восстановление — только CLI (R4). HTTP создаёт только
`restore_request`.

### 6.2. Создание запроса (HTTP, BackupPage)

1. Admin выбирает `backup_id`.
2. Re-auth (пароль) через существующий механизм Stage 8 + MFA панели.
3. Явное подтверждение.
4. `RestoreRequestService` создаёт `restore_requests`:
   `id` (UUID), `backup_id`, `admin_user_id`, `token` (HMAC-SHA256),
   `expires_at` (+30 мин), `status=pending`, `consumed_at=null`.
5. UI показывает `request_id` и инструкцию
   `SSH → php artisan app:rollback APPLY <request_id>`.

### 6.3. `CHECK` (read-only)

Проверки без изменений:

1. request существует, `status=pending`, не истёк;
2. HMAC-подпись валидна;
3. backup и артефакты существуют;
4. `hash_db` целостность;
5. пять проверок совместимости (§6.4).

### 6.4. Пять проверок совместимости (§17)

1. `php_version` доступна;
2. `laravel_version` поддерживается;
3. `extensions` все присутствуют;
4. `db_schema_version` совместима;
5. `hash_db` целостность.

Провал — restore запрещён, audit, изменения не выполняются.

### 6.5. `APPLY`

1. Проверки §6.3–§6.4.
2. Захват `BackupLock`.
3. Автоматический backup текущего состояния (full, Scope A).
4. Maintenance mode ON.
5. Восстановление БД из dump (текущее соединение).
6. Восстановление файлов из архива.
7. Maintenance mode OFF.
8. `/up` + smoke-test.
9. Audit `restore.applied`.
10. `status=applied`, `consumed_at=now()`.

Порядок «БД → файлы» фиксирован. Восстановление `.env` из Scope B меняет
`APP_KEY` и инвалидирует сессии — ожидаемое следствие полного rollback.

### 6.6. Провал

- Провал до изменения данных (шаги 1–2) — отказ без изменений,
  `status=failed`, audit.
- Провал после начала восстановления — maintenance mode остаётся
  включённым, `status=failed`, audit, ручное вмешательство. Автооткат к
  автоматическому backup не выполняется (риск потери данных).

### 6.7. Истёкший/использованный запрос

Отклоняется без изменений. Для повторного восстановления создаётся новый
запрос.

---

## §7. CLI

### 7.1. `app:backup`

```
php artisan app:backup --mode=full|site|db --scope=a|b --triggered-by=...
```

- `--mode` — `full` | `site` | `db`.
- `--scope` — `a` (`storage_app`) | `b` (`whole_site`); обязателен для
  `full`/`site`, не применяется для `db`.
- `--triggered-by` — `cron` | `deploy` | `admin:{id}` | `cli`.

### 7.2. `app:backup-cleanup`

Применяет retention (§9). В E9.2 — каркас, реализация — E9.3.

### 7.3. `app:rollback`

```
php artisan app:rollback LIST
php artisan app:rollback CHECK <id>
php artisan app:rollback APPLY <id>
```

`<id>` — `request_id` из `restore_requests`. `LIST` выводит backup'ы и
manifest-сводку.

### 7.4. `app:restore-test`

```
php artisan app:restore-test <id>
```

В E9.5 — каркас, реализация — E9.7 (§11).

### 7.5. Makefile

```
make backup [MODE=...] [SCOPE=...]
make rollback LIST
make rollback CHECK <id>
make rollback APPLY <id>
make restore-test <id>
```

---

## §8. `BackupPage`

- Filament-страница, группа «Система», `navigationSort` после Settings.
- Только admin (Policy). Роли/permissions Stage 8 не меняются.
- Функции: создание backup (radio mode + radio scope + warning для
  Scope B), список, просмотр manifest, download, delete, restore-request.
- Список отображает: дату/время, тип, scope, размер, статус, создателя.
- Размер backup — отображаемое свойство; пользовательских квот нет.
- Restore-request: re-auth + MFA + подтверждение → создаёт request и
  показывает инструкцию для CLI.

---

## §9. Retention

### 9.1. GFS-ротация

- daily — последние 7 календарных дней (по одному новейшему backup);
- weekly — последние 4 ISO-недели;
- monthly — последние 12 календарных месяцев.

Защищённое множество — объединение. Backup вне множества — кандидат на
удаление. Один backup может быть защищён несколькими группами.

### 9.2. Источник параметров

`settings`: `backup_retention_daily=7`, `backup_retention_weekly=4`,
`backup_retention_monthly=12`. Size-quota нет.

### 9.3. Free-space safety

- Перед созданием backup: если после ожидаемого backup остаток свободного
  места < `min_free_space_pct` (default `5`) — отказ, ошибка, audit.
- Ожидаемый размер — по размеру БД и scope.
- `app:backup-cleanup` освобождает место через GFS-удаление.

---

## §10. Scheduler

В `bootstrap/app.php` (`withSchedule`):

- `04:00` — `app:backup --mode=db --triggered-by=cron`, `withoutOverlapping()`;
- `04:30` — `app:backup-cleanup`, `withoutOverlapping()`.

Daily backup — DB-only (`is_complete=false`). Full backup — вручную или
перед deploy; автоматического daily full нет.

---

## §11. Restore-test

- Отдельная dev-БД `laravel_ffxi_restore_test` (конфигурация через env).
- `app:restore-test <id>`: восстановление в изолированную БД, не
  затрагивая рабочую; smoke-test; очистка тестовой БД.
- `make restore-test <id>`.

---

## §12. Audit и security

- Audit — через существующий `App\Services\AuditLogger`
  (`admin_audit_logs`). Новая audit-архитектура не создаётся.
- События: `backup.created`, `backup.deleted`, `backup.cleanup`,
  `restore.requested`, `restore.applied`, `restore.failed`,
  `restore.rejected`.
- IP фиксируется только для security-событий (restore/rollback),
  согласно решениям Stage 8.
- Backup хранится вне web root (`storage/backups/`).
- Пароль БД не попадает в список процессов (temp `--defaults-extra-file`).
- HTTP не выполняет restore.

---

## §13. Тестирование (сценарии)

Unit / feature-сценарии Stage 9:

1. Manifest: обязательные поля; `db_only` ⇒ `scope=null`,
   `is_complete=false`; `full`/`site_only` ⇒ `is_complete=true`.
2. Валидация режим/scope: `full`/`site_only` без scope — ошибка;
   `db_only` со scope — scope игнорируется/`null`.
3. `BackupService`: создание full / site_only / db_only; корректные
   артефакты, manifest, хэши.
4. Scope A: архив содержит `storage/app/`.
5. Scope B: архив содержит состав ADR-004 §3; исключает `vendor/`,
   `node_modules/`, `storage/backups/`.
6. `LocalBackupStorage`: put/get/exists/delete/size/list.
7. `BackupLock`: повторный захват — отказ; освобождение.
8. Retention: 7/4/12 — отбор кандидатов; защищённые не удаляются.
9. Free-space safety: недостаток места — отказ без создания backup.
10. `app:backup` CLI: режимы/scope/`--triggered-by`.
11. `app:backup-cleanup` CLI: удаляет только незащищённые.
12. `restore_requests`: создание, HMAC, expiry 30 мин.
13. `app:rollback CHECK`: валидный/истёкший/подделанный token.
14. `app:rollback APPLY`: полный flow с auto-backup и maintenance.
15. Провал совместимости — отказ без изменений + audit.
16. HTTP не выполняет restore (нет прямого вызова).
17. `BackupPage`: доступ только admin; editor/user — отказ.
18. `BackupPage`: список/создание/download/delete/manifest.
19. Warning для Scope B.
20. Scheduler: задачи зарегистрированы с `withoutOverlapping()`.
21. `app:restore-test`: изолированная БД, не затрагивает рабочую.
22. Audit-события создаются; IP только для security-событий.
23. Регрессия: Stage 1–8 не сломаны.

Главный критерий — покрытие Acceptance Criteria контракта, а не число
тестов.

---

## §14. Definition of Done Stage 9

- [ ] `BackupService` работает в режимах `full`/`site_only`/`db_only`
      и обоих scopes, без инфраструктурных зависимостей.
- [ ] Manifest содержит `type`, `scope`, `is_complete` и поля §17.
- [ ] `storage/backups/` вне Git; `.gitignore` обновлён.
- [ ] CLI `app:backup` и `make backup` работают.
- [ ] Retention `7/4/12` + free-space safety реализованы.
- [ ] `BackupPage` (admin-only) реализует создание/список/manifest/
      download/delete/restore-request/warning Scope B.
- [ ] `restore_requests` + HMAC-SHA256 + expiry 30 мин; restore только
      через CLI; HTTP restore отсутствует.
- [ ] Scheduler: daily DB-only backup + cleanup, `withoutOverlapping()`.
- [ ] `app:restore-test` в изолированной БД.
- [ ] Audit через существующий `AuditLogger`.
- [ ] Pest, Pint, Larastan, `git diff --check` проходят.
- [ ] `docs/acceptance/stage-9.md` отражает результаты и gaps.

---

## §15. Ссылки

- `docs/adr/ADR-004-backup-restore.md`.
- `docs/ai/context.md` §7, §11, §17, §22, §23, §24, §27, §30, §32, §38.
- `docs/ai/frontend-spec.md` §7.5.
- `docs/ai/STAGE-8-CONTRACT.md`.
