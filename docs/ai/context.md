# ТЗ: FFXI Phoenix community portal — Laravel + Filament

**Версия:** 4.0 (FINAL)
**Назначение:** основной технический контекст проекта.
**Файл:** docs/ai/context.md
**Статус:** рабочий документ, сопровождается вместе с кодом.

---

# ПРЕАМБУЛА — ПРИОРИТЕТНА НАД ВСЕМ

1. Если инструмент, пакет, команда или возможность не существует или
   нет уверенности в её существовании — сказать об этом прямо и
   предложить реальный проверенный аналог. Не выдумывать.
2. Код предоставлять полностью, без сокращений и placeholder-ов.
3. Генерация или изменение файлов — только после подтверждения плана
   согласно разделу 34.
4. Коммуникация — русский. Код, комментарии, commit messages — английский.
5. Не принимать технические предположения за факты. Проверять версии
   и совместимость.
6. При противоречии, несовместимости или опасном решении — остановиться
   и сообщить до продолжения.
7. Приоритет — безопасность, поддерживаемость, предсказуемые обновления,
   длительный жизненный цикл.

---

# РОЛЬ

Tech Lead и DevOps-архитектор. Проект ведётся одним разработчиком
несколько лет. Решения — с расчётом на длительную эксплуатацию.
Не усложнять без необходимости.

---

# 1. КОНТЕКСТ ПРОЕКТА

## Что это

**FFXI Phoenix community portal** — русскоязычное сообщество игроков
Final Fantasy XI (сервер Phoenix).

- **Публичная часть:** новости, галерея, статические страницы, события.
- **Сообщество (auth):** страницы игроков, каталог, дашборд (чат, события,
  активность), кабинет, уведомления.
- **Админка Filament:** управление контентом, пользователями, правами,
  справочниками, виджетами, аудитом, backup, обновлениями.

Единая модель данных, авторизации, приватности и прав. Не набор
независимых CRUD.

## Команда

1 разработчик; 1–2 редактора и 1 администратор через админ-панель.
Отдельной DevOps-команды нет.

## Нагрузка

Низкий трафик; до нескольких тысяч фотографий; высокая важность
сохранности данных; долгосрочная эксплуатация.

## Разработка

WSL2 + Ubuntu + Docker + Docker Compose. Проект — в ext4 WSL2,
не в /mnt/c.

## Production

Timeweb shared hosting, `https://ffxi.ru`.

Предполагается: PHP, MySQL, SSH, cron, SMTP, панель, SSL.
Не предполагается: Docker, Redis, Supervisor, pcntl, долгоживущие
процессы.

Возможности аккаунта проверяются на этапе 0 (раздел 7).

## Секреты

SSH, БД, SMTP — только в production `.env`. Не в Git, не в
документации, не в generated code, не передаются AI.

---

# 2. ОСНОВНЫЕ ПРИНЦИПЫ

Приоритеты: безопасность → поддерживаемость → обновляемость →
предсказуемость → откат → простота → минимум инфраструктуры.

## Переносимость

Код не знает, где работает. Отличия dev/CI/prod — только через `.env`.
Docker — среда разработки, не целевая платформа.

## Обновления

Контролируемо, через CI, ручной smoke-test, с возможностью отката.
Не в production напрямую.

## Backup и rollback

Backup без версионирования — не backup. Каждый backup фиксирует
данные + версии (PHP, Laravel, Filament, schema, extensions).
Rollback разрешён только при совместимости.

---

# 3. СТЕК

## Принято

| Компонент | Constraint |
|---|---|
| PHP | `~8.4.0` |
| laravel/framework | `^13.33` |
| filament/filament | `^5.8` |
| livewire/livewire | `^4.4` |
| spatie/laravel-permission | `^8.3` |
| intervention/image | `^4.3` |
| pestphp/pest | `^5.2` (dev) |
| pestphp/pest-plugin-laravel | `^5.0` (dev) |
| larastan/larastan | `^3.12` (dev) |
| laravel/pint | `^1.32` (dev) |
| MySQL | `8.4` |

Фактические версии — в `composer.lock` / `package-lock.json`.
При расхождении таблицы и lock — приоритет у lock.

## Разрешено добавить (фиксируется в ADR при подключении)

- `league/commonmark` — Markdown;
- `pragmarx/google2fa` + `bacon/bacon-qr-code` — 2FA Filament (если нужно);
- `ezyang/htmlpurifier` — опционально как HTML-санитайзер (fallback —
  собственный `HtmlSanitizer` на DOMDocument);
- npm: `chart.js` (виджеты), `photoswipe` (галерея; fallback — Alpine).

## Запрещено

Redis, Memcached, SQLite, Supervisor/pcntl, S3/MinIO,
`laravel/fortify`, `bezhansahu/filament-shield`, вебсокеты,
обязательные долгоживущие workers.

## Правило тай-брейка

Дольше security-окно → шире покрытие плагинами → короче путь обновлений.
Спорные случаи решает владелец проекта.

---

# 4. DEV-ОКРУЖЕНИЕ

WSL2 + Docker Compose:

    app      — PHP-FPM 8.4
    web      — Nginx 1.24-alpine   (8080→80)
    db       — MySQL 8.4           (3310→3306)
    node     — Node 22-alpine      (5173→5173)
    mailpit  — SMTP                (8025→8025)

Порты нестандартные — 80 и 3309 заняты другим проектом.

Healthchecks + `depends_on: service_healthy` для db.

`.gitattributes`: `* text=auto eol=lf`.

PHP 8.4 = dev = CI = production.

---

# 5. DATABASE

MySQL 8.4, utf8mb4. Dev/CI — контейнер `mysql:8.4`; prod — MySQL Timeweb.
SQLite запрещён.

---

# 6. RUNTIME-DRIVERS

    CACHE_STORE=file
    SESSION_DRIVER=database
    QUEUE_CONNECTION=database
    MAIL_MAILER=smtp

Redis/долгоживущие workers не используются. Чат и 🔔 — Livewire polling
с инкрементальной догрузкой. Очереди — через cron `schedule:run`.

---

# 7. PRODUCTION TIMEWEB — АУДИТ

Чек-лист (этап 0, ADR-002):

    php -v
    php -m
    php -i | grep -E 'memory_limit|max_execution_time|upload_max_filesize|post_max_size'
    which flock mysqldump tar gzip
    composer --version
    git --version
    php -r 'var_dump(function_exists("proc_open"));'
    ls -la ~
    symlink: возможность создания
    cron: 1 минута
    df -h

Дополнительно:

- PHP CLI = PHP web;
- extensions: pdo_mysql, mbstring, intl, zip, gd|imagick, fileinfo,
  exif, curl, openssl;
- ionCube не ломает composer/artisan;
- запись в backup-каталог вне web root;
- storage:link работает;
- /up работает;
- MySQL utf8mb4.

Результаты — в ADR-002.

---

# 8. DOCUMENT ROOT

Основной вариант: `domain → project/public`.
Fallback: симлинк `public_html → project/public`.
index.php-обёртка — крайний случай с фиксацией в ADR.

---

# 9. STORAGE

`storage/app/public → public/storage` (`php artisan storage:link`).
Публичные файлы — статика. Контроллер выдачи — только для защищённых.

---

# 10. QUEUE

`QUEUE_CONNECTION=database`. Worker запускается cron'ом:

    flock -n ~/run/queue.lock php artisan queue:work --stop-when-empty --max-time=55

Fallback при отсутствии flock — mkdir-блокировка.

Database queue допускает повторное выполнение job. Критичные — идемпотентны.

---

# 11. SCHEDULER

Cron: `* * * * * php artisan schedule:run`.

Обслуживает:

- queue:work --stop-when-empty --max-time=55 (каждую минуту);
- публикация отложенных новостей;
- напоминания о событиях (за 1 час);
- очистка activities по retention;
- backup БД (ежедневно, ADR-004);
- retention cleanup backup (ежедневно);
- /up health probe.

Все задачи — `->withoutOverlapping()` (file-lock). Предпочтительно
`->call()` / `Artisan::call()` (in-process).

---

# 12. ИЗОБРАЖЕНИЯ

Полная спецификация — `frontend-spec.md` §6.6 (ImageProcessor).
Ключевое:

- Валидация: MIME по факту (`finfo`+`getimagesize`), размер, разрешение,
  мегапиксели;
- Пере-кодирование (анти-полиглот), EXIF-strip;
- 3 размера: original ≤2560, medium 1280 WebP, thumb 480 WebP;
- Имена-хэши, `storage/app/public/photos/{Y}/{m}/`;
- SVG и исполняемые — запрет;
- Обработка — queued job;
- Nginx: `location ~* ^/storage/.*\.php$ { deny all; }` — в деплой.

---

# 13. SECURITY

Production: `APP_DEBUG=false`.

`.env` — не в Git, вне web root. `.env.example` — в Git, без реальных
credentials.

## Валидация

Все входные данные. FormRequest где оправдано. Blade-escaping по
умолчанию. `{!! !!}` — только после санитизации.

## CSRF, rate limiting

CSRF для web-запросов. Rate limits: логин 5/мин/IP, регистрация
5/час/IP, reset 3/час, комментарии 1/15с + 20/час, чат 5/30с,
фото 10/партия + 50/день, новости 10/день, события 5/день,
resend verification 3/день.

## Роли

    admin   — полный доступ
    editor  — только «Контент» в админке
    user    — авторизованный игрок
    guest   — неавторизованный (трекается, см. §13.10)

**Guest-трекинг.** Middleware `IdentifyGuest`: cookie `guest_uid`
(httpOnly, **30 дней**) → запись в `guest_visitors` (`display_name`
= `guest001…`, `ip_hash` = sha256(ip+app-key)). Конверсия в
пользователя — `converted_user_id`, `converted_at`. Видимость гостей —
только админка (`guests.view`).

## Матрица доступа

**Реализация:** spatie `section.{key}.view` (для ролей) + JSON
`guest_sections` в `settings` (для гостей). Middleware
`section.access:{key}`: auth → проверка permission; guest → проверка
JSON. `Gate::before` для admin возвращает `true` только для abilities
с префиксом `section.`. Для остальных abilities действуют обычные
Spatie permissions и Policies; явные запреты Policies сохраняются.
Изменения — мгновенны (flush settings cache).

Полная матрица и список секций — `frontend-spec.md` §4.

## MFA

Штатные возможности Filament 5. Обязательна для admin (`admin_2fa_required`,
default true). Editor — опционально.

## Password hashing

bcrypt или Argon2id — по конфигурации PHP.

## Security headers

Strict-Transport-Security, X-Content-Type-Options, X-Frame-Options: DENY,
Referrer-Policy, CSP (Report-Only → enforcing после проверки).

## Session security

Production cookies: `Secure`, `HttpOnly`, `SameSite`. Regeneration
после login. Logout инвалидирует. Reset-токены — с ограниченным сроком.

## Upload security

Allowlist MIME. Имена генерирует приложение. Каталоги загрузок
не исполняют PHP/CGI. Backup вне HTTP-доступа.

## Destructive admin actions

Restore, финальное удаление, смена ролей/прав, security-настройки —
Policy + re-auth (пароль) + audit. ActivityLog read-only для редакторов.

## Cookie и ПД-комплаенс

- Cookie-баннер — **информирование** (без «принять/отклонить»):
  «Мы используем технические cookie для работы сайта (сессия, тема,
  идентификатор гостя — 30 дней). Данные не передаются третьим лицам.»
- Страница `/cookie` — полный список cookie.
- Страница `/privacy` — политика обработки ПД.
- При регистрации — **два отдельных чекбокса**: «Согласен на обработку
  персональных данных» (обязательный) + «Согласен получать письма»
  (опциональный, default выкл).
- В `users`: `pd_consent_at`, `pd_policy_version`, `marketing_consent_at`.
- Никаких внешних аналитик (GA, Метрика, CDN) в MVP.
- IP-хэш; телефон — encrypted; email не публикуется.

---

# 14. FILAMENT

Админ-контур `/admin`. Визуально отделён от публичного сайта.
Полное описание ресурсов — `frontend-spec.md` §7.

## Resources (12)

    UserResource, RoleResource, GuestResource,
    NewsResource, CommentResource, GalleryResource,
    PageResource, EventResource, EventTypeResource,
    RankResource, DashboardWidgetResource, ActivityLogResource

## Кастомные страницы (4)

    PermissionsMatrixPage — матрица доступа
    SettingsPage          — настройки (большая таблица)
    BackupPage            — backup и rollback (§17, §24)
    UpdatePage            — обновления (§20)

## Навигация (4 группы)

    Основное      — Dashboard
    Сообщество    — Users, Roles, Matrix, Guests
    Контент       — News, Comments, Gallery, Pages, Events
    Система       — EventTypes, Ranks, Widgets, ActivityLog, Settings, Backup, Update

## Доступ

- Все ресурсы — Policies.
- RoleResource, BackupPage, UpdatePage, SettingsPage — только admin.
- Editor — группа «Контент» (News, Comments, Gallery, Pages, Events).
- ActivityLogResource — admin + editor (read-only).
- GuestResource — admin.

Первый администратор — production-safe механизм; пароль не в Git.
Демо-seeder отделён от production.

---

# 15. TESTING

Pest на MySQL 8.4. SQLite запрещён.

Минимальные сценарии — `frontend-spec.md` §9 (26 приёмочных + unit).
DoD каждого этапа — сценарии соответствующего блока зелёные,
Pint/Larastan чисто.

---

# 16. MIGRATIONS

expand → migrate/backfill → contract.
`down()` — не rollback. Rollback — предыдущая версия кода или
восстановление из backup.

---

# 17. BACKUPS

## Общее

Ежедневно (cron) + перед каждым production deployment + по требованию
из BackupPage.

**Full backup** = dump БД + `storage/app/` + manifest. DB-only
допускается как технический, но помечается неполным.

Ротация: 7 daily / 4 weekly / 12 monthly. Обязательна копия вне
Timeweb (ADR-004).

## Manifest

    backup_id            UUID
    created_at           ISO-8601
    triggered_by         cron | deploy | admin:{user_id}
    app_version          git tag или SHA
    app_changelog_hash   sha256(CHANGELOG.md)
    php_version          факт
    laravel_version      факт из composer.lock
    filament_version     факт
    livewire_version     факт
    composer_lock_hash   sha256
    package_lock_hash    sha256
    db_schema_version    последняя миграция
    db_migrations        список
    extensions           {pdo_mysql, mbstring, ...}
    db_size_bytes
    files                список
    hash_db              sha256 dump
    hash_files           sha256 files (если включён)
    created_by_user_id   user_id (не криптоподпись)

## Именование

    storage/backups/{YYYY}/{MM}/{backup_id}.{dump|files}.{gz|tar.gz}
    storage/backups/{YYYY}/{MM}/{backup_id}.manifest.json

`storage/backups/` не в Git.

## Проверка перед rollback

1. `php_version` доступна;
2. `laravel_version` поддерживается;
3. `extensions` все;
4. `db_schema_version` совместима;
5. `hash_db` целостность.

Провал — rollback запрещён без явного подтверждения и записи в
ActivityLog.

## BackupPage

- Список backup'ов с фильтрами (cron/deploy/admin);
- Просмотр manifest;
- «Создать backup»;
- «Восстановить» — только admin, MFA + re-auth + явное подтверждение +
  выбор по `backup_id` + проверка совместимости + audit;
- Перед restore — автоматический backup текущего состояния;
- Maintenance mode + блокировка параллельных deploy/backup/restore;
- Restore — через CLI (§24), не в HTTP;
- После — `/up` + smoke-test;
- «Скачать manifest.json»;
- Настройка retention.

## Restore-test

Ежемесячно в отдельном dev-окружении. Проверяется реальное
восстановление.

---

# 18. MONITORING

Внешний uptime на `/up`. Healthcheck для cron-задач (healthchecks.io
или аналог — ADR-005). Без Redis/Supervisor.

---

# 19. DEPENDENCIES

Без `*`. Constraints `^`, `~` или точные. Lock-файлы коммитятся.

Production: `composer install --no-dev --optimize-autoloader`.
`composer update` на production запрещён.

---

# 20. DEPENDENCY UPDATES

## Patch

Dependabot/Renovate PR + merge при зелёном CI.

## Minor

PR + CI + тесты + review + changelog.

## Major

Ветка `upgrade/<component>-<version>`. Обязательно: upgrade-док,
breaking changes, код, CI, smoke-test, staging, rollback, backup.
Не на production напрямую.

## Триггер

Приближение EOL security-поддержки — старт обновления сразу.
Laravel major — не реже раза в год.

## UpdatePage

Только просмотр и подготовка:

- текущие версии;
- доступные обновления (`composer outdated --direct`, `npm outdated`);
- «Проверить обновления» (read-only);
- история из ActivityLog;
- «Скачать upgrade-report».

Кнопка «Обновить сейчас» — отсутствует.

## `make upgrade-check <component> <version>`

1. ветка `upgrade/...`;
2. обновление манифестов;
3. `composer update <package> --with-all-dependencies`;
4. полный набор тестов;
5. Pint + Larastan;
6. сборка frontend;
7. отчёт в `docs/adr/upgrades/`.

---

# 21. CI

На PR:

    composer validate
    composer audit
    pint --test
    larastan (level 6 ориентир)
    pest (MySQL 8.4)

PHP — как в production.

---

# 22. DEPLOYMENT

`build in dev/CI → rsync → Timeweb`.

## make deploy

Перед:

1. git status чистый;
2. HEAD = release tag;
3. CI зелёный;
4. release version определена.

Шаги:

1. `composer install --no-dev --optimize-autoloader` в Docker;
2. `npm ci && npm run build` в Docker;
3. MySQL backup (с метаданными);
4. `rsync -az --delete --exclude-from=deploy/rsync-exclude.txt`;
5. `storage:link`;
6. `php artisan migrate --force`;
7. `optimize:clear → config:cache → route:cache → view:cache`;
8. `/up`;
9. smoke-test.

Окно rsync→migrate — безопасно при expand-contract.

**Nginx:** запрет исполнения PHP в `/storage` — в деплой-конфиг.

---

# 23. DEPLOY EXCLUDE

    .env*
    .git/
    .github/
    docker/
    docs/
    tests/
    node_modules/
    storage/app/            ← КРИТИЧНО
    storage/logs/
    storage/backups/        ← КРИТИЧНО
    storage/framework/cache/

Неполный exclude + `--delete` = потеря данных.
Проверяется при review любого изменения deploy-скрипта.

---

# 24. ROLLBACK

## Термины

- **code rollback** — предыдущий код при совместимой БД;
- **full rollback / restore** — код + БД + файлы из backup.

## A. Code rollback

    git checkout <previous-tag>
    make deploy

## B. Full rollback

1. Автоматический backup текущего состояния;
2. BackupPage → `backup_id` (не путь);
3. Проверка совместимости (§17);
4. Maintenance mode + блокировка;
5. Restore через CLI;
6. `/up` + smoke-test + audit.

Провал проверки — отказ с записью в audit.

## Паттерн на shared hosting

Restore — только CLI через SSH:

1. Admin в BackupPage: MFA + re-auth → создаётся `restore_request`
   (UUID, `backup_id`, `admin_user_id`, `token` HMAC-SHA256,
   `expires_at` +30 мин);
2. BackupPage: «SSH → `make rollback APPLY <request_id>`»;
3. CLI проверяет token/срок → блокировка → backup текущего состояния →
   maintenance → проверка совместимости → restore → `/up` → audit →
   status;
4. Истёк/провал — отказ без изменений.

HTTP-вызов restore запрещён.

## Цена

Rollback глубже одного совместимого релиза — потеря данных после точки
backup.

## CLI

    make rollback LIST
    make rollback CHECK <id>
    make rollback APPLY <id>
    make restore-test <id>

---

# 25. GIT

Ветки: `main`, `feature/*`, `fix/*`, `upgrade/*`, `release/*`.
Deploy — только по release tag. SemVer. CHANGELOG.md вручную.

---

# 26. PROJECT STRUCTURE

    app/
    database/
    tests/
    docker/
    deploy/
    docs/
    .github/

Расширенно:

    app/
    database/{migrations,seeders}
    tests/{Feature,Unit}
    docker/{nginx,php,mysql}
    deploy/{rsync-exclude.txt, cron.txt, deploy.sh, upgrade.sh, rollback.sh}
    docs/
        adr/
            upgrades/
        ai/
        design/{wireframe.html, admin.html}
        legal/
            privacy-policy-draft.md
        designreview.md
        adminreview.md
        frontend-spec.md
    .github/workflows/

Структура `app/Filament` — по конвенциям Filament 5.

---

# 27. MAKEFILE

    make up | down | build
    make test | lint | lint-test | stan | audit | validate
    make deploy | rollback | backup | restore-test
    make upgrade-check

Без backend-implementation команд не создавать.
backup / rollback / restore-test / upgrade-check — этапы 9, 13.

---

# 28. DOCUMENTATION

README: требования, WSL2, Docker, `.env`, миграции, seeders, тесты,
frontend, deploy, Timeweb, rollback, backup, restore-test, обновления,
чек-листы.

ADR — короткие: проблема / решение / причины / альтернативы /
последствия.

Design-документы — §37.

---

# 29. TIMEWEB STAGING

Промежуточный перенос на поддомен до финала. Проверка PHP, extensions,
MySQL, document root, `.env`, storage, queue, cron, scheduler, uploads,
mail, `/up`, permissions, cache, deploy, backup, restore, rollback.

Инфраструктура — второй каталог, вторая БД, отдельные cron (ADR).

---

# 30. ЭТАПЫ

Общий критерий: `make test` зелёный, CI зелёный, ручная проверка,
осмысленный commit.

Правило: этап N+1 не начинается, пока N не закрыт по приёмкам
`frontend-spec.md` §9 (для функциональных 3–8) или по общим критериям
(для технических 0–2, 9–15).

## Этап 0 — аудит

- Timeweb по §7 → ADR-002.
- Аудит текущего репо: routes, controllers, models, migrations, policies,
  Filament, views, Livewire, CSS, БД, auth. Gap-отчёт. ADR-003 (slug-стратегия),
  ADR-006 (HtmlSanitizer).

Блокирует 12–14, не блокирует 1–11.

## Этап 1 — фиксация стека

ADR-001 (уже принят). Проверка совместимости при изменениях.

## Этап 2 — Docker dev + skeleton

5 контейнеров, healthchecks, Laravel 13 skeleton, `/up`, lock-файлы.

## Этап 3 — Auth, роли, фундамент

- Регистрация, live-проверка ника (`NicknameSuggester`), анти-enumeration,
  верификация email, reset.
- ПД-согласие (2 чекбокса), `pd_consent_at`, `pd_policy_version`.
- spatie-роли/разрешения, `section.{key}.view`, `settings`.
- `guest_visitors` + `IdentifyGuest`.
- Middleware: `section.access`, `registration.open`.
- Статусная модель `users`.
- Layout из `wireframe.html` + cookie-баннер + `/cookie` + `/privacy`.
- Главная.

## Этап 4 — Content engine

- `ContentRenderer` + `HtmlSanitizer` + XSS-датасет.
- Новости (site + player, post/pre, archived, comments_enabled,
  «Редакция FFXI.ru»).
- Страницы.
- Комментарии (полиморфные, reputation, spam, «пожаловаться»).

## Этап 5 — Галерея

Альбомы, `ImageProcessor`, страницы фото, PhotoSwipe, пакетная загрузка.

## Этап 6 — Профили

- Страница игрока, `<x-user-identity>`, `<x-user-rank>`, `<x-social-links>`.
- Приватность (`is_profile_public`).
- Каталог `/players/directory`.
- Кабинет `/cabinet/{tab}`: профиль, соцсети, новости, фото, события,
  безопасность, опасная зона (7.12 frontend-spec).
- Предпросмотр.
- Аватары в `storage/app/public/avatars/`.

## Этап 7 — Дашборд

- Чат (Livewire polling, @упоминания, антифлуд, модерация).
- События (+ типы, `registration_close`, участники, бизнес-правила).
- Activity feed (+ `comment_created`, группировка).
- Online users.
- 🔔 (Community + Personal, mark-as-read).

## Этап 8 — Filament (полный)

- Dashboard (real-статы, графики, шорткаты).
- Users, Roles, Matrix, Guests.
- News, Comments, Gallery, Pages, Events.
- EventTypes, Ranks, Widgets.
- ActivityLog, Settings.
- Delete-requests очередь + re-auth.
- 2FA, IP allowlist, audit.

## Этап 9 — Backup/restore/rollback

Backup с метаданными, BackupPage, restore через CLI
(`restore_request`), restore-test, CLI-команды.

## Этап 10 — Tests

Полное покрытие `frontend-spec.md` §9 на MySQL 8.4.

## Этап 11 — CI

composer validate, audit, Pint, Larastan, Pest.

## Этап 12 — Timeweb staging

Поддомен по §29.

## Этап 13 — Deployment

rsync, cron, queue, scheduler, storage, cache, nginx php-deny,
deploy.sh / rollback.sh / upgrade.sh.

Базовый update-workflow CLI (`make upgrade-check`) — с этого этапа.

## Этап 14 — Production

SSL, `.env`, backup, monitoring, `/up`, permissions, cron, queue, mail,
storage, smoke-test. Первый полный backup, репетиция rollback.

## Этап 15 — Update mechanism (UI)

UpdatePage, расширенный `make upgrade-check`, регламент обновлений.
Поверх CLI-workflow этапа 13.

---

# 31. ИТОГОВЫЙ РЕЗУЛЬТАТ

Development: `docker compose up -d` на чистой WSL2 → smoke-test.

Production: Timeweb без Docker, Redis, Supervisor, pcntl.

Возможности: новости, галерея, профили, дашборд (чат/события/активность),
кабинет, соцсети, ранги, регистрация (управляемая), админ-панель, роли,
матрица доступа, MFA, комментарии, изображения, 🔔, backup с проверкой
совместимости, rollback, restore-test, обновления, scheduler, monitoring,
CI.

---

# 32. ЗАПРЕЩЕНО

- wildcard `*` в версиях;
- `.env`, `vendor/`, `node_modules/`, backup-файлы, storage-артефакты,
  `storage/backups/` в Git;
- `composer update` на production;
- deployment без зелёного CI;
- deployment без backup;
- rollback без проверки совместимости;
- автообновление production из админки;
- restore БД из HTTP-запроса;
- `rsync --delete` с неполным exclude;
- хардкод секретов и абсолютных путей Timeweb;
- SQLite как основная test DB;
- расхождение dev/CI/prod;
- зависимость production от Docker, Redis, Supervisor, pcntl,
  долгоживущих workers;
- `down()` migration как гарантированный rollback;
- credentials в документации и коде;
- первый production без staging;
- пакет без проверки существования и поддержки;
- выдуманные команды/пакеты/API;
- placeholder вместо рабочего кода.

---

# 33. ПРИНЦИП РАБОТЫ AI

1. Понять текущую архитектуру.
2. Проверить код.
3. Проверить версии.
4. Не ломать без причины.
5. Сообщать о проблемах.
6. Не скрывать uncertainty.
7. Не выдумывать возможности.
8. Не обновлять только ради новизны.
9. Security update — с обоснованием.
10. Major update — с breaking changes.

Внешний пакет — только после проверки: существование, совместимость,
актуальность, поддержка, необходимость. Задача, решаемая стандартными
средствами, пакетом не закрывается.

---

# 34. ФОРМАТ РАБОТЫ

## Шаг 1

Резюме архитектуры; версии; обоснование; проверка совместимости; список
зависимостей; структура; список файлов; порядок разработки; риски.
Стоп, ждать подтверждения.

## Шаг 2

Файлы по одному, полностью. Перед — назначение. После — что проверить.

Стандартные файлы framework skeleton (config, bootstrap, public,
artisan) — через официальные инструменты (`composer create-project`,
`php artisan make:*`, `php artisan vendor:publish`). Правило «по одному
файлу» — для авторских (модели, контроллеры, миграции, ADR, docker-конфиги).

## Шаг 3

При противоречии / несовместимости / опасном решении — стоп, доклад,
ожидание решения.

---

# 35. НОВАЯ СЕССИЯ

1. Запросить `docs/ai/context.md`, `docs/ai/frontend-spec.md`, статус
   репозитория (ветка, коммиты, этапы).
2. Определить выполненные этапы.
3. Не начинать с нуля.
4. Не переопределять ADR без причины.

---

# 36. ГЛАВНЫЙ ПРИНЦИП

> **Безопасное, поддерживаемое, предсказуемое веб-приложение с
> длительным жизненным циклом: разработка в WSL2 + Docker, перенос
> на shared hosting Timeweb, безопасные обновления без сложной
> инфраструктуры.**

Версия PHP/Laravel/Filament — средство, не цель.

---

# 37. DESIGN DOCUMENTS

## Список

| Файл | Назначение |
|---|---|
| docs/ai/context.md | Технический контекст (этот файл) |
| docs/ai/frontend-spec.md | Функционал публичной части и взаимодействия |
| docs/designreview.md | Дизайн публичного сайта |
| docs/adminreview.md | Дизайн админ-контура |
| docs/design/wireframe.html | Макет публичной части |
| docs/design/admin.html | Макет админки |
| docs/legal/privacy-policy-draft.md | Заготовка политики ПД |

## Приоритет при конфликте

- Стек, безопасность, деплой, backup, обновления → **context.md**.
- Функционал (модели, маршруты, роли, бизнес-логика, приёмки) →
  **frontend-spec.md**.
- Вёрстка, страницы, компоненты, UX → **designreview.md + wireframes**.
- Админ-ресурсы, матрица, аудит → **adminreview.md**.

При отсутствии ответа в design-документах — вопрос пользователю до
генерации файлов. Не выдумывать.

## Открытые вопросы

См. §38.

---

# 38. OPEN QUESTIONS

## Из designreview.md §11

1. Поля карточки игрока («Data 1 / Data 2», «Calcula / Promathia»,
   «Robots / Jumxi») — заглушки или реальные данные?
2. Список файлов `public/img_site` — из архива сайта.

## Из adminreview.md §13

3. RichEditor: TinyMCE / Tiptap / встроенный Filament?
4. Мультиязычность в админке: нужна или только RU?
5. Экспорт данных: CSV / XLSX / визуальная заглушка?

## Из ТЗ v4.0

6. Внешнее хранилище backup: S3 / restic / скачивание вручную?
7. Тексты политики обработки ПД и cookie — от владельца проекта
   (юрист). До получения — регистрация приостановлена
   (`registration_open=false`).
8. Список соцплатформ — финальный (12 из frontend-spec §6.3).

**Закрыто в v4.0** (ранее открытые): ActivityLog (свои таблицы),
Settings (key-value), Хранение аватаров (storage/app/public/avatars/),
Объём backup (full), Формат backup (БД + файлы + manifest),
Механизм обновлений (CLI + UpdatePage-информация).

До закрытия вопроса AI не выбирает архитектурное решение молча.
Низкорисковые визуальные детали — по макету с записью в CHANGELOG/ADR.

---

# ИСТОРИЯ ВЕРСИЙ

**4.0** — интеграция функциональной спецификации (frontend-spec.md v1.0):
роли + guest-трекинг (`guest_visitors`, cookie 30 дней), матрица доступа
через `section.{key}.view` spatie + guest-JSON, комментарии (полиморфные),
`/players` (dashboard/directory/player page), собственные `activities` +
`admin_audit_logs`, единый план этапов 0–15, cookie/ПД-комплаенс
(`pd_consent_at`, `pd_policy_version`, `marketing_consent_at`,
cookie-баннер-информирование, `/cookie`, `/privacy`), новые ресурсы
Filament (12 + 4 страницы), `ImageProcessor`, `NicknameSuggester`,
`ContentRenderer` + `HtmlSanitizer`, 2FA admin, расширенный rollback
через CLI (`restore_request`). Стек не изменён (PHP 8.4, Laravel 13.33+,
Filament 5.8+, Livewire 4.4+, MySQL 8.4).

**3.8.1** — три уточнения: restore на shared hosting через CLI
(`restore_request`); §38/Q14 приведён к §17; разграничены этапы 13 и 15.

**3.8** — финализация ядра: зафиксированы PHP 8.4, Laravel 13.33.0,
Filament 5.8.4, Livewire 4.4.6, MySQL 8.4; backup manifest на
runtime/lock-версиях; `signed_by` → `created_by_user_id`; full backup =
БД + файлы + manifest; усилены restore/rollback, session/upload security,
destructive-actions, ActivityLog.

**3.7** — design-документы в структуре проекта: §37 DESIGN DOCUMENTS,
§38 OPEN QUESTIONS; расширены §1, §13, §14, §15, §17, §20, §22, §23,
§24, §26, §27, §30, §31, §32, §34.

**3.5** — уточнение §30: этап 0 блокирует 12–14, не блокирует 1–11.

**3.4** — критическое исправление: `storage/app/` в deploy exclude,
принцип переносимости, тай-брейк, ionCube в аудит, Dependabot/Renovate,
окно rsync→migrate, staging в ADR, дедупликация, code-блоки только
для команд/путей/конфигураций.

**3.3** — рецензируемая база.
