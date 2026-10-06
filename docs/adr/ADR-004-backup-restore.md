# ADR-004: Подсистема backup / restore / rollback

| Поле | Значение |
|---|---|
| Статус | Принят |
| Дата | 2026-10-06 |
| Этап | 9 — Backup/restore/rollback (context.md §30) |
| Связанные разделы | context.md §7, §9, §10, §11, §13, §17, §22, §23, §24, §27, §32, §38 (Q6); frontend-spec.md §7.5; ADR-001, ADR-007, ADR-008 |
| Причина выделения | context.md §17 и §24 ссылаются на ADR-004 как источник решения по внешнему хранилищу backup; Q6 (§38) остаётся открытым; требуется зафиксировать scope, restore-flow и retention до реализации Stage 9 |

---

## 1. ПРОБЛЕМА

context.md §17 требует ежедневный backup с метаданными, ротацией
`7 daily / 4 weekly / 12 monthly` и «обязательной копией вне Timeweb
(ADR-004)». §24 описывает restore на shared hosting через CLI и
`restore_request`. §38 Q6 оставляет открытым вопрос внешнего хранилища
(S3 / restic / скачивание вручную).

При этом:

1. **Внешнее хранилище не определено.** S3/MinIO прямо запрещены
   context.md §3 и §32, поэтому вариант «S3» из Q6 неприменим. Без
   решения по Q6 нельзя выполнить требование «обязательная копия вне
   Timeweb».
2. **Объём backup неоднозначен.** §17 определяет full backup как
   «dump БД + `storage/app/` + manifest», но Stage 9 вводит режим
   «весь сайт» (Scope B), точный состав которого в spec не задан.
3. **restore-flow не детализирован.** §24 задаёт общую схему
   (`restore_request`, HMAC, expiry), но не фиксирует порядок проверок,
   блокировок и поведение при провале.
4. **Retention не формализован.** `7/4/12` — это GFS-схема, но
   конкретный алгоритм отбора и поведение при нехватке места не описаны.
5. **Точка расширения внешнего хранилища.** Даже при отложенном
   внешнем хранилище архитектура должна позволить добавить его позже
   без переписывания сервиса.

Настоящий ADR фиксирует решения до реализации Stage 9, чтобы E9.1–E9.8
не требовали архитектурных решений владельца.

---

## 2. РЕШЕНИЯ (R1–R12)

### R1. Единая application-level подсистема

Создаётся единый `App\Services\Backup\BackupService`, пригодный и для
локальной разработки (WSL2 + Docker), и для production на Timeweb.
Архитектура не зависит от Docker, Redis, Supervisor и долгоживущих
workers. Запуск — только через Artisan CLI (вручную или через
`schedule:run`).

### R2. Режимы backup

Поддерживаются три режима:

| Режим | Содержимое | Scope | `is_complete` |
|---|---|---|---|
| `full` | DB dump + файловая часть | обязателен (`storage_app` или `whole_site`) | `true` |
| `site_only` | только файловая часть | обязателен (`storage_app` или `whole_site`) | `true` |
| `db_only` | только DB dump | `null` | `false` |

`is_complete = false` **не означает** ошибку, повреждение или
невалидность backup. DB-only — валидная полная резервная копия базы
данных, но она не содержит файловой части сайта. `is_complete`
отражает полноту относительно full site snapshot, а не качество.

### R3. BackupPage в полном объёме Stage 9

Filament-страница `BackupPage` (группа «Система», только admin)
реализует: создание backup, список, просмотр manifest, download,
delete, restore-request, предупреждение для Scope B.

### R4. Восстановление — только через CLI

Фактическое восстановление выполняется исключительно CLI-командой
`app:rollback APPLY`. HTTP-запрос (Filament action, контроллер) не
выполняет restore и не изменяет файлы/БД. HTTP создаёт только
`restore_request`.

### R5. `restore_requests`

Отдельная таблица БД `restore_requests`. Токен — HMAC-SHA256, ключ —
`APP_KEY`. `expires_at` — +30 минут.

### R6. Retention

Базовая счётная ротация: `7 daily / 4 weekly / 12 monthly` (GFS).
Дополнительно — safety-проверка свободного места. Фиксированных
size-quota («не более N GB») и произвольной size-based rotation нет.
Размер backup отображается и учитывается safety-механизмом.

### R7. Scheduler

Ежедневный scheduler создаёт **DB-only** backup (`is_complete=false`) и
запускает retention cleanup. Full backup выполняется вручную и перед
deploy; автоматического ежедневного full backup нет.

### R8. `mysqldump` в dev

В `docker/php/Dockerfile` добавляется `default-mysql-client` — это
dev-инфраструктурное требование для `mysqldump`. Production-архитектура
от Docker не зависит.

### R9. HMAC-подпись manifest

HMAC-подпись manifest не реализуется и не требуется. Целостность
проверяется по `hash_db` / `hash_files` (§17) и по обязательным полям
манифеста.

### R10. CHANGELOG.md

Создаётся минимальный `CHANGELOG.md`. Поле manifest
`app_changelog_hash` = `sha256(CHANGELOG.md)`.

### R11. Audit

Используется существующий `App\Services\AuditLogger`
(таблица `admin_audit_logs`). Новая audit-архитектура не создаётся.
IP фиксируется только для security-событий, согласно решениям Stage 8.

### R12. Scope B — фиксированный состав

Scope B определяется только точным составом из §3 настоящего ADR.
Состав не расширяется и не сокращается в рамках Stage 9.

---

## 3. SCOPE B — ТОЧНЫЙ СОСТАВ

Scope B — «полный site snapshot»: набор данных, необходимый для
восстановления работоспособного сайта на чистом хостинге вместе с
БД (при режиме `full`).

### 3.1. Включаемые каталоги (рекурсивно)

| Путь | Назначение | Примечание |
|---|---|---|
| `app/` | код приложения | |
| `bootstrap/` | bootstrap Laravel | исключая `bootstrap/cache/*.php` (регенерируется) |
| `config/` | конфигурация | |
| `database/` | миграции, сидеры, фабрики | |
| `public/` | точка входа, собранные ассеты | исключая `public/storage` (симлинк) и `public/hot` |
| `resources/` | Blade, CSS, JS-исходники | |
| `routes/` | маршруты | |
| `lang/` | локализация (RU и другие) | кастомные переводы приложения (amendment E9.2) |
| `storage/app/` | пользовательские данные (Scope A) | фотографии, аватары, вложения |

### 3.2. Включаемые корневые файлы

| Файл | Назначение |
|---|---|
| `artisan` | CLI-точка входа |
| `composer.json`, `composer.lock` | зависимости PHP |
| `package.json`, `package-lock.json` | зависимости frontend |
| `phpunit.xml` | конфигурация тестов |
| `phpstan.neon` | конфигурация анализа |
| `vite.config.js` | сборка frontend |
| `.editorconfig` | правила форматирования |
| `.gitattributes` | правила Git |
| `.gitignore` | правила игнорирования |
| `.env.example` | шаблон окружения (без секретов) |
| `Makefile` | цели разработки/деплоя |
| `CHANGELOG.md` | история версий |
| `KODA.md` | инструкции проекта |
| `.env` | **включается** (см. §3.4) |

### 3.3. Явно исключаемые пути

| Путь | Причина |
|---|---|
| `vendor/` | регенерируется `composer install --no-dev --optimize-autoloader` из `composer.lock` (§22) |
| `node_modules/` | регенерируется `npm ci` из `package-lock.json` |
| `.git/` | метаданные VCS; code rollback — через git, не через backup |
| `docker/` | dev-only инфраструктура, не используется в production (§1, §22) |
| `docs/` | документация, не требуется для работы сайта |
| `tests/` | не нужны в production (§23) |
| `.github/` | CI-конфигурация (§23) |
| `.vscode/`, `.idea/`, `.fleet/` | IDE |
| `.kodarules` | AI-tooling, не runtime сайта |
| `storage/logs/` | runtime-логи (§23) |
| `storage/framework/` | cache/sessions/views/testing — регенерируется |
| `storage/backups/` | рекурсия внутрь самих backup'ов (критично) |
| `public/storage` | симлинк, пересоздаётся `storage:link` |
| `public/hot` | dev-маркер Vite |
| `bootstrap/cache/*.php` | регенерируется `optimize` |

### 3.4. `.env` и секреты

`.env` **включается** в Scope B, поскольку без него full site snapshot
не восстанавливается как работоспособный сайт (`APP_KEY`, доступ к БД,
SMTP). Следствие: backup Scope B содержит секреты приложения и является
**чувствительным артефактом**.

Обязательное требование: при создании backup в режиме с Scope B
(`full` + `whole_site`) BackupPage и CLI обязаны выводить
предупреждение о том, что архив содержит `.env` и секреты.

`vendor/` в Scope B **не входит** (см. §3.3): он воспроизводим из
`composer.lock`/`composer.json`, которые входят в snapshot. Это
осознанное решение в рамках принятой модели деплоя (§22).

---

## 4. MANIFEST

Манифест — JSON-файл, сопровождающий каждый backup. Полный набор полей
согласован с context.md §17 и расширен обязательными полями Stage 9:
`type`, `scope`, `is_complete`, а также `format_version` и `artifacts`.

### 4.1. Обязательные поля Stage 9

| Поле | Тип | Значение |
|---|---|---|
| `format_version` | int | версия формата манифеста (Stage 9 = `1`) |
| `backup_id` | string (UUID) | идентификатор backup |
| `type` | string | `full` \| `site_only` \| `db_only` |
| `scope` | string \| null | `storage_app` \| `whole_site` \| `null` |
| `is_complete` | bool | `true` для `full`/`site_only`, `false` для `db_only` |

### 4.2. Поля из context.md §17

| Поле | Тип | Источник |
|---|---|---|
| `created_at` | string (ISO-8601) | `now()` |
| `triggered_by` | string | `cron` \| `deploy` \| `admin:{user_id}` \| `cli` |
| `app_version` | string \| null | `git describe --tags --always` (fallback — версия из deploy-файла или `null`) |
| `app_changelog_hash` | string | `sha256(CHANGELOG.md)` |
| `php_version` | string | `PHP_VERSION` |
| `laravel_version` | string | `composer.lock` |
| `filament_version` | string | `composer.lock` |
| `livewire_version` | string | `composer.lock` |
| `composer_lock_hash` | string | `sha256(composer.lock)` |
| `package_lock_hash` | string | `sha256(package-lock.json)` |
| `db_schema_version` | string | последняя запись таблицы `migrations` |
| `db_migrations` | array | список записей таблицы `migrations` |
| `extensions` | object | карта `extension => bool` по списку §7 |
| `db_size_bytes` | int | размер БД |
| `files` | array | список включённых путей |
| `hash_db` | string \| null | `sha256` dump-файла |
| `hash_files` | string \| null | `sha256` файлового архива |
| `created_by_user_id` | int \| null | актор (для cron/deploy — `null`) |

### 4.3. Дополнительные поля Stage 9

| Поле | Тип | Назначение |
|---|---|---|
| `size_bytes` | int | суммарный размер артефактов backup |
| `artifacts` | object | карта `db`/`files` → относительный путь артефакта |

### 4.4. Пример

```json
{
  "format_version": 1,
  "backup_id": "3f2b1c4e-...",
  "type": "full",
  "scope": "whole_site",
  "is_complete": true,
  "created_at": "2026-10-06T04:00:00+00:00",
  "triggered_by": "admin:1",
  "created_by_user_id": 1,
  "app_version": "v0.9.0",
  "app_changelog_hash": "sha256:...",
  "php_version": "8.4.26",
  "laravel_version": "13.33.0",
  "filament_version": "5.8.4",
  "livewire_version": "4.4.6",
  "composer_lock_hash": "sha256:...",
  "package_lock_hash": "sha256:...",
  "db_schema_version": "0001_01_01_000024_add_suspended_fields_to_users_table",
  "db_migrations": ["..."],
  "extensions": {"pdo_mysql": true, "mbstring": true},
  "db_size_bytes": 12345678,
  "files": ["app", "bootstrap", "config", "...", "storage/app"],
  "hash_db": "sha256:...",
  "hash_files": "sha256:...",
  "size_bytes": 987654321,
  "artifacts": {"db": "2026/10/3f2b1c4e-....dump.gz", "files": "2026/10/3f2b1c4e-....files.tar.gz"}
}
```

HMAC-подпись манифеста не добавляется (R9).

---

## 5. RETENTION

### 5.1. Счётная ротация (GFS)

Ротация применяется ко всем backup'ам, отсортированным по `created_at`
по убыванию:

- **daily** — для каждого из последних 7 календарных дней сохраняется
  самый новый backup этого дня;
- **weekly** — для каждой из последних 4 ISO-недель сохраняется самый
  новый backup этой недели;
- **monthly** — для каждого из последних 12 календарных месяцев
  сохраняется самый новый backup этого месяца.

Объединение сохранённых backup'ов — защищённое множество. Backup, не
попавший ни в одну из групп, является кандидатом на удаление. Один
backup может быть защищён несколькими группами.

### 5.2. Safety по свободному месту

- Перед созданием backup проверяется свободное место: если после
  ожидаемого backup остаток свободного места меньше `min_free_space_pct`
  (default `5`), создание прерывается с ошибкой и записью в audit.
- Ожидаемый размер оценивается по размеру БД (для DB) и размеру scope
  (для файлов).
- Отдельной size-quota на backup-хранилище нет.

### 5.3. Источники конфигурации

| Параметр | Источник | Default |
|---|---|---|
| `backup_retention_daily` | `settings` (DB) | `7` |
| `backup_retention_weekly` | `settings` (DB) | `4` |
| `backup_retention_monthly` | `settings` (DB) | `12` |
| `min_free_space_pct` | `settings` (DB) | `5` |

Статические параметры (путь, бинарники, версия формата, отключённое
внешнее хранилище) — в `config/backup.php`. Один runtime-параметр имеет
ровно один источник истины.

---

## 6. RESTORE FLOW

### 6.1. Общая схема

```
BackupPage → restore_request → re-auth / MFA → защищённый request
           → CLI → CHECK → APPLY
```

HTTP не выполняет восстановление (R4).

### 6.2. Создание запроса (HTTP)

1. Admin выбирает `backup_id` в BackupPage.
2. Выполняется re-auth (пароль) через существующий механизм Stage 8 и
   требование MFA панели.
3. Явное подтверждение.
4. `RestoreRequestService` создаёт строку в `restore_requests`:
   `id` (UUID), `backup_id`, `admin_user_id`, `token` (HMAC-SHA256),
   `expires_at` (+30 минут), `status = pending`, `consumed_at = null`.
5. BackupPage показывает `request_id` и инструкцию:
   `SSH → php artisan app:rollback APPLY <request_id>`.

### 6.3. Проверка (CLI, `app:rollback CHECK`)

Read-only: существование request, срок, статус, HMAC-подпись, наличие
backup, целостность (`hash_db`), совместимость (§6.4). Ничего не
изменяет.

### 6.4. Пять проверок совместимости (§17)

1. `php_version` доступна;
2. `laravel_version` поддерживается;
3. `extensions` все присутствуют;
4. `db_schema_version` совместима;
5. `hash_db` целостность.

Провал любой проверки — restore запрещён, запись в audit, изменения не
выполняются.

### 6.5. Применение (CLI, `app:rollback APPLY`)

1. Проверки из §6.3 и §6.4.
2. Захват блокировки (`BackupLock`) — исключение параллельных
   backup/restore.
3. Автоматический backup текущего состояния (full, Scope A).
4. Maintenance mode ON.
5. Восстановление БД из dump (текущее соединение).
6. Восстановление файлов из архива.
7. Maintenance mode OFF.
8. `/up` + smoke-test.
9. Audit (`restore.applied`).
10. `restore_requests.status = applied`, `consumed_at = now()`.

Порядок «сначала БД, затем файлы» фиксирован. Восстановление `.env`
из Scope B меняет `APP_KEY` и инвалидирует активные сессии — это
ожидаемое следствие полного rollback.

### 6.6. Провал

- Провал на шаге 1–2 — отказ без изменений, `status = failed`, audit.
- Провал после начала восстановления — maintenance mode остаётся
  включённым, `status = failed`, audit, ручное вмешательство. Автооткат
  к автоматическому backup не выполняется (риск потери данных).

### 6.7. Истёкший запрос

Истёкший или уже использованный `restore_request` отклоняется без
изменений. Для повторного восстановления создаётся новый запрос.

---

## 7. АЛЬТЕРНАТИВЫ

| Вариант | Решение |
|---|---|
| Внешнее хранилище S3/MinIO в Stage 9 | Отклонён: запрещено context.md §3/§32. Отложено до post-production через abstraction `BackupStorage`. |
| Собственный дампер на PDO вместо `mysqldump` | Отклонён: переизобретение `mysqldump`, риск на типах/больших таблицах (§33). |
| Новый Composer-пакет для архивов/дампов | Отклонён: задача решается `tar`/`gzip` и `mysqldump` (§33). |
| HMAC-подпись manifest | Отклонён: не требуется §17 (R9). |
| Restore из HTTP | Отклонён: §24 прямо запрещает. |
| `is_complete=true` для DB-only | Отклонён: DB-only не содержит файловой части сайта. |
| Size-quota backup-хранилища | Отклонён: вводится счётная ротация + free-space safety (R6). |
| Включение `vendor/` в Scope B | Отклонён: воспроизводим из `composer.lock`; увеличивает архив без пользы. |

---

## 8. ПОСЛЕДСТВИЯ

### 8.1. Положительные

- Единый `BackupService` для dev и production, без инфраструктурных
  зависимостей.
- Точный состав Scope B — воспроизводимый site snapshot.
- Restore только через CLI — снижает риск случайного/удалённого
  восстановления.
- `restore_request` с HMAC и коротким сроком жизни — контролируемый
  доступ к необратимой операции.
- Внешнее хранилище подключается позже через `BackupStorage` без
  переписывания `BackupService`.

### 8.2. Отрицательные и принятые риски

1. **Внешняя копия не реализуется в Stage 9** (Q6). Требование §17
   «обязательная копия вне Timeweb» остаётся невыполненным до
   post-production. Фиксируется как известный gap приёмки Stage 9.
2. **Scope B содержит `.env` и секреты.** Митигация: обязательное
   предупреждение в BackupPage/CLI, хранение backup вне web root.
3. **Restore `.env` меняет `APP_KEY`.** Митигация: HMAC-проверка
   выполняется до восстановления; инвалидация сессий — ожидаемое
   следствие полного rollback.
4. **Провал restore после начала** оставляет maintenance mode.
   Митигация: автоматический backup перед восстановлением, отказ от
   автоотката, ручное вмешательство.
5. **`app_version` на production без `.git`** может быть `null`.
   Митигация: deploy-файл версии на Stage 13; `null` допустим.

### 8.3. Условия пересмотра

- Появление внешнего хранилища (Q6) — новый ADR или расширение §R1/R2
  без изменения `BackupService`.
- Изменение модели деплоя (§22), влияющее на состав Scope B.
- Требование HMAC-подписи manifest или шифрования dump.
- Необходимость отката без ручного вмешательства при провале restore.

---

## 9. ССЫЛКИ

| Документ | Роль |
|---|---|
| `docs/ai/context.md` §17 | Backup: manifest, ротация, проверки, BackupPage |
| `docs/ai/context.md` §24 | Rollback: code/full, restore_request, CLI |
| `docs/ai/context.md` §11, §22, §23, §27, §30, §32 | Scheduler, deploy, exclude, Makefile, этапы, запреты |
| `docs/ai/context.md` §38 Q6 | Открытый вопрос внешнего хранилища |
| `docs/ai/STAGE-9-CONTRACT.md` | Контракт реализации Stage 9 |
| `docs/adr/ADR-007-access-control-source-of-truth.md` | Модель доступа |
| `docs/adr/ADR-008-stage-7-decisions.md` | Решения Stage 7 |
