# ТЗ: FFXI Phoenix community portal — Laravel + Filament

**Версия:** 3.8.1 (FINAL)
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
   согласно разделу «ФОРМАТ РАБОТЫ».
4. Коммуникация — русский язык. Код, комментарии в коде и commit
   messages — английский.
5. Не принимать технические предположения за факты. Если решение
   зависит от конкретной версии Laravel, Filament, PHP, Timeweb или
   пакета — сначала проверить совместимость.
6. При обнаружении противоречия в ТЗ, проблем совместимости или
   потенциально опасного решения — остановиться и сообщить о проблеме
   до продолжения работы.
7. Приоритет проекта — безопасность, поддерживаемость, предсказуемые
   обновления и длительный жизненный цикл.

---

# РОЛЬ

Ты работаешь как **Tech Lead и DevOps-архитектор** проекта.

Проект ведётся одним разработчиком в течение нескольких лет. Решения
принимаются с расчётом на длительную эксплуатацию, регулярные
обновления зависимостей и переносимость между dev и production.

Не усложнять архитектуру без необходимости.

---

# 1. КОНТЕКСТ ПРОЕКТА

## Что это

**FFXI Phoenix community portal** — русскоязычное сообщество игроков
Final Fantasy XI (сервер Phoenix). Реконструкция классического сайта-
сообщества 2000-х на современном стеке Laravel с сохранением духа
оригинала и добавлением современных UX-практик.

Публичная часть: новости, галерея, игроки, инфо-страницы, авторизация.
Административный контур: управление контентом, пользователями, правами,
журнал действий, backup и обновления.

## Команда

1 разработчик; 1–2 редактора и 1 администратор работают через
административную панель. Отдельной DevOps-команды нет.

## Нагрузка

Низкий трафик информационного сайта сообщества; до нескольких тысяч
фотографий в галерее; высокая важность сохранности данных; долгосрочная
эксплуатация без планового переписывания.

## Разработка

Единственная среда разработки:

    WSL2 + Ubuntu + Docker + Docker Compose

Проект хранится в файловой системе WSL2 (ext4), не в /mnt/c.

## Production

Timeweb, виртуальный (shared) хостинг.

Предполагается наличие: PHP, MySQL, SSH, cron, SMTP, панели
управления, SSL.

Не предполагается: Docker, Redis, Supervisor, pcntl, постоянно
работающие worker-процессы и другие долгоживущие процессы.

Все фактические возможности конкретного аккаунта Timeweb проверяются
до разработки production deployment (раздел 7).

## Секреты

SSH, БД, SMTP и другие credentials: только в .env production; не
находятся в Git; не попадают в документацию, generated code и не
передаются AI в явном виде.

---

# 2. ОСНОВНЫЕ ПРИНЦИПЫ

Приоритеты:

1. Безопасность.
2. Поддерживаемость в течение нескольких лет.
3. Возможность регулярного обновления зависимостей.
4. Предсказуемость production deployment.
5. Возможность отката.
6. Простота эксплуатации одним разработчиком.
7. Отсутствие ненужной инфраструктуры.

## Принцип переносимости

Код не знает, где работает. Все отличия dev/CI/production — только
через .env и конфигурацию сборки. Приложение пишется под лимиты
shared-хостинга, а не под Docker: Docker — среда разработки,
не целевая платформа.

## Принцип обновлений

Нельзя требовать, чтобы major-обновления никогда не ломали код.
Правильное требование:

> Обновления зависимостей, включая major-версии Laravel, выполняются
> контролируемо: не в production напрямую, только через CI, ручной
> smoke-test и с возможностью безопасного отката.

Приложение готовится не к заморозке на одной версии, а к регулярным
безопасным обновлениям.

## Принцип совместимости backup и rollback

Backup без проверки совместимости — это не backup. Каждый backup
фиксирует не только данные, но и версии (PHP, Laravel, Filament,
schema, extensions). Rollback разрешён только если целевая версия
совместима с текущей инфраструктурой или инфраструктура может быть
приведена к ней документированной процедурой.

---

# 3. ВЫБОР ВЕРСИЙ И СОВМЕСТИМОСТЬ

## PHP

Текущая принятая версия — **PHP 8.4** с constraint `~8.4.0`.

PHP 8.5 не является частью текущего принятого стека. Его переход
возможен только как отдельное будущее upgrade-решение после проверки
совместимости всего стека и production Timeweb с оформлением изменения
через ADR.

## Laravel

Принята и зафиксирована конкретная версия Laravel 13.33.0 с
constraint `^13.33`. Решение оформлено в ADR-001.

## Filament

Принят и зафиксирован Filament 5.8.4 с constraint `^5.8`.

## Livewire

Принят и зафиксирован Livewire 4.4.6 с constraint `^4.4`.

## Ядро принятого стека

| Компонент | Версия | Constraint / образ | Источник |
|---|---|---|---|
| **PHP** | **8.4** | `~8.4.0` | ADR-001, раздел 2.2 |
| **Laravel Framework** | **13.33.0** | `^13.33` | ADR-001, раздел 2.2 |
| **Filament** | **5.8.4** | `^5.8` | ADR-001, раздел 2.2 |
| **Livewire** | **4.4.6** | `^4.4` | ADR-001, раздел 2.2 |
| **MySQL** | **8.4** | `mysql:8.4` (Docker) | docker-compose.yml |

## Роли и права

| Пакет | Версия | Constraint | Источник |
|---|---|---|---|
| `spatie/laravel-permission` | **8.3.0** | `^8.3` | ADR-001, раздел 2.2 |

## Обработка изображений

| Пакет | Версия | Constraint | Источник |
|---|---|---|---|
| `intervention/image` | **4.3.2** | `^4.3` | ADR-001, раздел 2.2 |

## Dev-инструменты и тестирование

| Пакет | Версия | Constraint | Источник |
|---|---|---|---|
| `pestphp/pest` | **5.2.1** | `^5.2` | ADR-001, раздел 2.3 |
| `pestphp/pest-plugin-laravel` | **5.0** (major) | `^5.0` | composer.json |
| `larastan/larastan` | **3.12.2** | `^3.12` | ADR-001, раздел 2.3 |
| `laravel/pint` | **1.32.1** | `^1.32` | ADR-001, раздел 2.3 |
| `laravel/tinker` | **3.0** | `^3.0` | composer.json |
| `nunomaduro/collision` | **8.6** | `^8.6` | composer.json |
| `mockery/mockery` | **1.6** | `^1.6` | composer.json |
| `fakerphp/faker` | **1.23** | `^1.23` | composer.json |

## Docker-инфраструктура

| Компонент | Версия / образ | Источник |
|---|---|---|
| **app** (PHP-FPM) | `docker/php/Dockerfile` (база `php:8.4-fpm`) | docker-compose.yml |
| **web** (Nginx) | `nginx:1.24-alpine` | docker-compose.yml |
| **db** (MySQL) | `mysql:8.4` | docker-compose.yml |
| **node** (Vite) | `node:22-alpine` | docker-compose.yml |
| **mailpit** | `axllent/mailpit:latest` | docker-compose.yml |

## Порты (наружу / внутри)

| Сервис | Наружу | Внутри | Источник |
|---|---:|---:|---|
| **web** | **8080** | 80 | docker-compose.yml |
| **db** | **3310** | 3306 | docker-compose.yml |
| **node** | **5173** | 5173 | docker-compose.yml |
| **mailpit** | **8025** | 8025 | docker-compose.yml |
| **app** | не пробрасывается | 9000 | docker-compose.yml |

Порты **8080** и **3310** выбраны вместо стандартных **80** и **3309**,
потому что эти порты заняты другим проектом (Bitrix) на этой же WSL2-машине.

## Runtime-драйверы

| Параметр | Значение | Источник |
|---|---|---|
| `CACHE_STORE` | `file` | .env.example, раздел 6 ТЗ |
| `SESSION_DRIVER` | `database` | .env.example, раздел 6 ТЗ |
| `QUEUE_CONNECTION` | `database` | .env.example, раздел 6 ТЗ |
| `MAIL_MAILER` | `smtp` | .env.example, раздел 6 ТЗ |
| `APP_URL` (dev) | `http://localhost:8080` | .env.example |
| `APP_URL` (production) | `https://ffxi.ru` | зафиксировано в проекте |
| `DB_DATABASE` | `laravel_ffxi` | .env.example |
| `DB_USERNAME` | `laravel` | .env.example |
| `DB_PASSWORD` | `secret` (placeholder) | .env.example |
| `DB_ROOT_PASSWORD` | `root_secret` (placeholder) | .env.example |

Сочетание `secret` / `root_secret` является только локальным placeholder
для `.env.example`; реальные credentials никогда не переносятся из
него в production.

## Не используется (запрещено ТЗ)

| Компонент | Причина |
|---|---|
| **Redis** | ТЗ, раздел 6 — не используется |
| **Memcached** | ТЗ, раздел 6 — не используется |
| **SQLite** | ТЗ, разделы 5, 15, 32 — запрещён |
| **Supervisor / pcntl** | ТЗ, раздел 32 — shared hosting |
| **S3 / MinIO** | ТЗ, раздел 6 — не в базовой версии |
| **`laravel/fortify`** | Кандидат на этап 4, не входит в принятый стек |
| **`bezhansahu/filament-shield`** | Не используется; авторизация через Laravel Policies |

## Итоговая связка

**PHP 8.4 + Laravel 13.33.0 + Filament 5.8.4 + Livewire 4.4.6 +
MySQL 8.4 + Pest 5.2.1**

Принятые версии зафиксированы в `ADR-001-stack.md`, `composer.json` и
`composer.lock`. Фактически установленное состояние релиза определяется
`composer.lock`; при расхождении таблицы и lock-файла приоритет имеет
актуальный lock-файл после принятого изменения ADR.

## Правило тай-брейка

Если обе связки технически рабочие, выбирается та, у которой:
1) дольше окно security-поддержки; 2) шире покрытие нужными плагинами;
3) проще путь будущих обновлений.

Не выбирать старую версию только потому, что она «стабильнее по
ощущениям». Спорные случаи решает владелец проекта, не ИИ.

---

# 4. DEV-ОКРУЖЕНИЕ

WSL2 + Docker Compose. Сервисы:

    app    — PHP-FPM
    web    — Nginx
    db     — MySQL 8.4
    node   — Node.js/Vite
    mailpit — SMTP для локального тестирования почты

У каждого сервиса — healthcheck; миграции не запускаются раньше
готовности db (depends_on: condition: service_healthy).

Проект — в ext4-файловой системе WSL2, не в /mnt/c.

.gitattributes:

    * text=auto eol=lf

PHP-версия совпадает во всех средах:

    dev PHP 8.4 = CI PHP 8.4 = production PHP 8.4

Отклонение production по minor-версии — только с обоснованием и
фиксацией в ADR.

---

# 5. DATABASE

MySQL 8.4. Dev и CI — контейнер mysql:8.4; production — MySQL,
предоставляемый Timeweb. Кодировка utf8mb4.

SQLite не используется ни как production DB, ни как основная
CI/test DB. Тесты выполняются на MySQL 8.4, чтобы окружение
тестирования максимально соответствовало production.

---

# 6. RUNTIME-DRIVERS

    CACHE_STORE=file
    SESSION_DRIVER=database
    QUEUE_CONNECTION=database
    MAIL_MAILER=smtp

Redis не используется. Сессии через database сохраняются при обычном
deployment. Не использовать архитектурные решения, требующие Redis,
Supervisor или постоянно работающих процессов.

---

# 7. PRODUCTION TIMEWEB — АУДИТ

Production работает без Docker. Все фактические возможности сервера
сначала проверяются по SSH. Этот раздел — единственный полный
чек-лист аудита (этап 0 ссылается сюда).

Проверить:

    php -v
    php -m
    php -i | grep -E 'memory_limit|max_execution_time|upload_max_filesize|post_max_size'
    which flock
    which mysqldump
    which tar
    which gzip
    composer --version
    git --version
    which proc_open-проверка: php -r 'var_dump(function_exists("proc_open"));'
    структура каталогов (ls -la ~)
    symlink: возможность создания
    cron: доступность и минимальный интервал
    доступное место на диске (df -h)

Дополнительно:

- доступность PHP CLI и соответствие версии PHP CLI версии PHP web;
- необходимые PHP extensions (pdo_mysql, mbstring, intl, zip,
  gd или imagick, fileinfo, exif, curl, openssl);
- ionCube: проверить, что composer и artisan работают под лоадером;
- возможность cron-задач с интервалом 1 минута;
- возможность выполнения mysqldump (для backup);
- возможность выполнения tar/gzip (для backup);
- права на каталоги;
- работа storage:link;
- работа /up;
- подключение MySQL, версия, utf8mb4;
- возможность записи в каталог backup вне web root;
- доступное место на диске (важно для backup).

Результаты фиксируются в ADR-002.

---

# 8. DOCUMENT ROOT

Основной и предпочтительный вариант:

    domain → project/public

Web server обслуживает непосредственно project/public. Web-доступ к
корню Laravel-проекта не открывается.

Если Timeweb не позволяет назначить public/ как document root —
используется симлинк:

    public_html → project/public

index.php-обёртка или .htaccess-rewrite — только крайний вариант
после проверки безопасности (при index.php-обёртке патч стокового
файла обязателен к записи в ADR: правка теряется при обновлениях
фреймворка, если её не задокументировать).

Выбранный вариант фиксируется в ADR.

---

# 9. STORAGE

Стандартный Laravel-механизм:

    storage/app/public → public/storage

Проверить: php artisan storage:link (идемпотентно).

Публичные изображения — обычная раздача статических файлов.
Контроллер/маршрут выдачи файла — только для файлов, которые
действительно должны быть защищены авторизацией.

---

# 10. QUEUE

QUEUE_CONNECTION=database. В production нет постоянно работающего
worker; cron запускает worker периодически:

    php artisan queue:work --stop-when-empty --max-time=55

Защита от параллельного старта — flock:

    flock -n ~/run/queue.lock php artisan queue:work --stop-when-empty --max-time=55

Если flock недоступен — файловая блокировка через mkdir().

Важно:

> Database queue не гарантирует абсолютное отсутствие повторного
> выполнения job.

Поэтому: корректно настроить retry_after; ограничивать время
выполнения; критичные jobs проектировать идемпотентными; учитывать
возможность повторного запуска; использовать failed_jobs.

---

# 11. SCHEDULER

Cron Timeweb (через панель) запускает Laravel Scheduler, обычно раз
в минуту:

    php artisan schedule:run

Scheduler не зависит от внешних shell-команд без необходимости.
Предпочтительно: ->call() и Artisan::call() (выполнение in-process).
Использование ->command() и ->exec() — только после проверки
необходимости и реальных возможностей production.

Scheduler обслуживает:

- queue:work --stop-when-empty --max-time=55 (каждую минуту под flock);
- backup database (ежедневно, время фиксируется в ADR-004);
- backup retention cleanup (ежедневно);
- site health probe (для мониторинга, раздел 18).

---

# 12. ИЗОБРАЖЕНИЯ

Загрузка изображений проверяет минимум: MIME/type; реальный тип
файла; размер файла; разрешение; мегапиксели; допустимый формат.

Преобразования (resize, webp, thumbnail/presets) по возможности
выполняются через queue. Размер и сложность обработки соответствуют
ограничениям shared hosting (memory_limit, max_execution_time,
upload_max_filesize, post_max_size) — большие изображения не должны
ронять production PHP-процесс. Лимиты загрузки валидацией берутся от
фактических лимитов сервера (раздел 7), с запасом.

Ошибочные jobs попадают в failed_jobs.

---

# 13. SECURITY

Production: APP_DEBUG=false.

.env: не в Git; вне web root; минимальные права. .env.example: в Git,
с описанием переменных, без реальных credentials.

## Валидация

Все пользовательские входные данные валидируются. FormRequest — там,
где это оправдано. Blade: escaping по умолчанию; {!! !!} — только при
осознанной необходимости и безопасной подготовке данных.

## CSRF и rate limiting

CSRF-защита включена для соответствующих web-запросов.
Rate limiting обязателен: логин; операции с загрузкой файлов;
публичные формы после их появления.

## Роли и права

Четыре роли:

    admin   — полный доступ ко всему
    editor  — управление контентом (новости, галерея, страницы, события)
    user    — авторизованный игрок (просмотр закрытых разделов, чат)
    guest   — виртуальная роль (не в БД), обозначает «без авторизации»

Реализация:

- spatie/laravel-permission — источник истины по ролям и разрешениям.
- Laravel Policies — авторизация на уровне ресурсов и действий.
- Матрица доступа к публичным страницам — таблица `page_role_access`
  (см. adminreview.md §4). Middleware `EnsurePageAccess` читает
  page_slug из route name или параметра и сверяет с ролью текущего
  пользователя или `guest`.

Правила:

- Не полагаться только на скрытие элементов интерфейса Filament.
- Права проверяются и в UI, и в Policy.
- Admin не может удалить сам себя и не может снять с себя роль admin
  через UI.
- Матрица доступа кэшируется через CACHE_STORE=file; инвалидация при
  сохранении.

## Регистрация

Регистрация новых пользователей управляется настройкой сайта.
Текущее состояние: приостановлена (сайт работает как информационный
ресурс). Возможность включить/выключить — через страницу
«Настройки → Регистрация и доступ».

## MFA

Штатные возможности выбранной версии Filament. Конкретная реализация
проверяется на этапе 4. Применяется к ролям admin и editor.

## Password hashing

Стандартный безопасный механизм Laravel (bcrypt / Argon2id);
конкретный вариант — по поддерживаемой конфигурации PHP.

## Security headers

Минимально: Strict-Transport-Security, X-Content-Type-Options,
X-Frame-Options. CSP — сначала Report-Only; переход к enforcing
после проверки совместимости и отсутствия нарушений. HSTS — после
стабилизации HTTPS production.

## Session security

- Production session cookie: `Secure`, `HttpOnly`, `SameSite` по
  безопасной конфигурации приложения.
- После успешного login выполняется session ID regeneration.
- Logout инвалидирует текущую сессию и регенерирует CSRF/session state
  по возможностям выбранной версии Laravel.
- Password reset tokens имеют ограниченный срок действия и одноразовое
  использование.

## Upload security

- Используется явный allowlist допустимых расширений и MIME/type.
- Имена загруженных файлов генерируются приложением; исходное имя
  пользователя не используется как доверенный путь.
- Каталоги пользовательских загрузок не должны позволять исполнение
  PHP/CGI-кода.
- Backup-файлы и manifest хранятся вне HTTP-доступного пути либо
  дополнительно защищаются серверной конфигурацией.

## Destructive admin actions

Критические destructive-действия (restore, удаление данных и другие
операции с необратимым эффектом) требуют соответствующих Policy checks,
повторной аутентификации/MFA и явного подтверждения. ActivityLog
должен быть доступен для чтения администраторам и редакторам согласно
матрице доступа, но не должен позволять обычному пользователю изменять
или удалять записи журнала.

---

# 14. FILAMENT

Административная панель — отдельный контур на /admin. Не совпадает
визуально с публичным сайтом.

Полный перечень ресурсов и кастомных страниц — в docs/adminreview.md
(§5, §6, §7). В ТЗ фиксируется только объём и правила:

## Resources

    UserResource          — Users (CRUD, роли, блокировка, аватары)
    RoleResource          — Roles (только admin)
    NewsResource          — News (CRUD, публикация, расписание)
    CommentResource       — Comments (модерация bulk-действиями)
    GalleryResource       — GalleryItem (загрузка, обработка, presets)
    PageResource          — Pages (CRUD, матрица доступа, публикация)
    EventResource         — Events (события / формы, заявки)
    ActivityLogResource   — ActivityLog (read-only, фильтры)

## Кастомные страницы

    PermissionsMatrixPage — матрица доступа к публичным страницам
    SettingsPage          — общие, регистрация, логирование
    BackupPage            — backup и rollback (см. §17, §24)
    UpdatePage            — обновления и подготовка (см. §20)

## Доступ

- Все ресурсы закрыты Policies.
- RoleResource, BackupPage, UpdatePage — только admin.
- ActivityLogResource — admin и editor (просмотр).

Первый администратор создаётся через отдельный production-safe
механизм; пароль не находится в Git. Демо-seeder отделён от
production seeders и не запускается случайно на production.

---

# 15. TESTING

Использовать Pest. Тесты выполняются на MySQL 8.4. SQLite запрещён.

Минимальные критичные сценарии:

1. публикация новости;
2. создание/редактирование новости;
3. загрузка изображения;
4. валидация изображения;
5. доступ admin;
6. доступ editor;
7. доступ user;
8. запрет неавторизованного доступа к закрытым страницам;
9. работа Filament Resources;
10. публичный рендер страниц;
11. 404;
12. queue job;
13. failed job;
14. scheduler;
15. матрица доступа: middleware EnsurePageAccess;
16. матрица доступа: сохранение и инвалидация кэша;
17. модерация комментариев;
18. регистрация (когда включена);
19. смена пароля;
20. backup: создание, чтение метаданных, верификация;
21. rollback: проверка совместимости (без реального отката);
22. backup retention: удаление старых;
23. базовые security-sensitive сценарии.

---

# 16. MIGRATIONS

Стратегия expand → migrate/backfill → contract.

Новые поля: сначала nullable/default → backfill → переход
приложения → удаление старых полей отдельным следующим релизом.

down() не считается полноценным механизмом production rollback.
Rollback — через предыдущую версию кода, совместимую со схемой БД,
либо восстановление БД из backup.

---

# 17. BACKUPS

## Общее

Backup: ежедневно (cron) + перед каждым production deployment +
по требованию из админки (страница BackupPage).

**Полный production backup** — это восстанавливаемый снимок, включающий
дамп БД, пользовательские файлы и manifest. DB-only backup допускается
как быстрый технический backup, но не считается полноценным
восстанавливаемым snapshot.

Каждый backup связывается в manifest с конкретным application release
и состоянием окружения: backup_id, app_version, lock-файлы, версии
runtime, schema и extensions. Таким образом manifest фиксирует
совместимость данных и кода на момент создания backup.

Ротация: 7 daily / 4 weekly / 12 monthly. Обязательна копия вне
production-сервера — хранение только на Timeweb полноценной защитой
не считается. Внешнее хранилище — ADR-004.

## Метаданные backup

Каждый backup — это не только dump БД, а **версионированный снимок
системы**. Backup содержит:

    manifest.json:
    - backup_id           UUID
    - created_at          ISO-8601
    - triggered_by        cron | deploy | admin:{user_id}
    - app_version         git tag или commit SHA
    - app_changelog_hash  sha256(CHANGELOG.md)
    - php_version         фактическая версия PHP runtime
    - laravel_version     фактическая Composer-resolved версия из composer.lock
    - filament_version    фактическая Composer-resolved версия из composer.lock
    - livewire_version    фактическая Composer-resolved версия из composer.lock
    - composer_lock_hash  sha256(composer.lock)
    - package_lock_hash   sha256(package-lock.json)
    - db_schema_version   последняя применённая миграция (batch)
    - db_migrations       список применённых миграций
    - extensions          {pdo_mysql, mbstring, intl, zip, gd, ...}
    - db_size_bytes       размер дампа
    - files:              список файлов в архиве
    - hash_db             sha256 от dump.sql.gz
    - hash_files          sha256 от files.tar.gz (если включён)
    - created_by_user_id  user_id, создавший backup (для admin-triggered; не криптографическая подпись)

manifest.json не шифруется и хранится рядом с дампом.

## Что входит в backup

- Дамп БД (`dump.sql.gz`) — обязательно.
- `storage/app/` (пользовательские загрузки) — обязательно для полного
  production backup.
- `public/img_site/` — если используется как публичные ассеты.
- Для DB-only технического backup файлы могут отсутствовать, но такой
  backup явно маркируется как неполный и не подменяет полный snapshot.
- `.env` — **никогда** (секреты не в backup).
- `manifest.json` — обязательно.

## Именование

    storage/backups/{YYYY}/{MM}/{backup_id}.{dump|files}.{gz|tar.gz}
    storage/backups/{YYYY}/{MM}/{backup_id}.manifest.json

`storage/backups/` **не в Git** (см. §32).

## Версионирование и совместимость

Перед rollback (см. §24) система **обязана** проверить:

1. `php_version` backup — доступна ли на production.
2. `laravel_version` — поддерживается ли текущим PHP.
3. `extensions` — все ли установлены.
4. `db_schema_version` — совместима ли схема БД backup с кодом, к
   которому откатываемся.
5. `hash_db` — не повреждён ли дамп.

Если проверка не прошла — rollback **запрещён** без явного
подтверждения и внесения записи в журнал (ActivityLog) с причиной.

## Админ-страница BackupPage

Возможности:

- Список backup'ов по датам (с фильтрами cron / deploy / admin).
- Просмотр manifest.json каждого backup (раскрывающийся блок).
- Кнопка «Создать backup сейчас» (ручное).
- Кнопка «Восстановить» — только для admin; обязательны MFA, повторная
  аутентификация, явное подтверждение, выбор только по внутреннему
  `backup_id`, проверка совместимости и запись в ActivityLog.
- Перед production restore автоматически создаётся backup текущего
  состояния.
- На время restore блокируются параллельные deploy/backup/restore
  операции и включается maintenance mode.
- Restore выполняется контролируемой CLI/внешней процедурой, а не
  непосредственно внутри обычного HTTP-запроса админки.
- После restore обязательны проверка `/up` и smoke-test.
- Кнопка «Скачать manifest.json» (без dump).
- Настройка retention (7/4/12).

## Планирование

Cron через Scheduler (раздел 11). Время и частота — ADR-004.
Retention cleanup — ежедневно.

## Restore-test

Ежемесячно — restore-test в отдельном dev Docker environment.
Проверяется не наличие dump-файла, а реальная возможность
восстановить базу. Результат фиксируется в docs/adr.

---

# 18. MONITORING

Внешний uptime-monitor на endpoint /up. Cron-задачи дополнительно
контролируются через healthcheck-механизм (healthchecks.io или
аналог — ADR-005).

Monitoring не требует Redis, Supervisor или постоянно работающего
процесса.

---

# 19. DEPENDENCIES

Не использовать * в качестве версии. Constraints — осмысленные
(^, ~ или точная версия при необходимости). Установленные версии
фиксируются в composer.lock / package-lock.json; lock-файлы
обязательно коммитятся.

Production:

    composer install --no-dev --optimize-autoloader

composer update на production запрещён. Все обновления выполняются
в dev/CI.

---

# 20. DEPENDENCY UPDATES

## Общее правило

Обновление — это изменение версий PHP-пакетов, JS-пакетов или
runtime (PHP). Осуществляется контролируемо: dev → CI → staging →
production.

## Patch

Автоматизация: Dependabot или Renovate (composer + npm) создают PR;
merge при зелёном CI согласно правилам проекта.

## Minor

Отдельный PR: CI, тесты, review, проверка changelog/release notes.

Ежемесячно: composer outdated --direct — просмотр и планирование.

## Major

Отдельная ветка upgrade/<component>-<version>. Обязательно:

1. изучение официальной upgrade-документации;
2. проверка breaking changes;
3. обновление кода;
4. CI (полный набор тестов, §21);
5. ручной smoke-test;
6. проверка на staging (§29);
7. подготовка rollback (см. §24);
8. **backup перед deployment** (§17).

Major update не выполняется напрямую на production.

## Обязательный триггер major-обновления

Приближение EOL security-поддержки текущей major-версии — повод
начать обновление сразу. Laravel major updates — плановый ориентир
не реже раза в год.

## Админ-страница UpdatePage

Возможности:

- Просмотр текущих версий (PHP, Laravel, Filament, Node, БД).
- Просмотр доступных обновлений (через `composer outdated --direct`
  и `npm outdated` в dev-контейнере; вывод кэшируется).
- Кнопка «Проверить обновления» (запускает read-only проверку).
- Список последних успешных обновлений (из ActivityLog).
- Кнопка «Скачать upgrade-report» — сводка по текущему состоянию.

**Кнопка «Обновить сейчас» отсутствует.** Обновления выполняются
через dev/CI/staging и деплой. UpdatePage — только информация и
подготовка, чтобы администратор видел состояние, но не мог
автоматически обновить production.

## Тесты как подготовка

Скрипт `make upgrade-check <component> <version>`:

1. создаёт ветку upgrade/<component>-<version>;
2. обновляет composer.json / package.json;
3. выполняет composer update <package> --with-all-dependencies;
4. запускает полный набор тестов (§15);
5. запускает Larastan и Pint;
6. собирает frontend;
7. возвращает отчёт: что сломано, что требует ручной правки.

Отчёт сохраняется в docs/adr/upgrades/ для истории.

---

# 21. CI

На каждый Pull Request:

    composer validate
    composer audit
    Laravel Pint --test
    Larastan
    Pest

Тесты — на MySQL 8.4. Larastan — актуальный поддерживаемый уровень
(ориентир — level 6). CI использует ту же PHP major/minor версию,
что и production.

---

# 22. DEPLOYMENT

Основная стратегия:

    build in dev/CI → rsync → Timeweb

Composer и Node не обязаны присутствовать на production — deployment
переносит готовые vendor/ и public/build/.

## make deploy

Перед deployment:

1. git status чистый;
2. HEAD соответствует аннотированному release tag;
3. CI зелёный;
4. release version определена.

Шаги:

1. composer install --no-dev --optimize-autoloader — в Docker;
2. npm ci && npm run build — в Docker;
3. создать MySQL backup ДО изменения production (с фиксацией
   метаданных, §17);
4. rsync:

       rsync -az --delete --exclude-from=deploy/rsync-exclude.txt

   Флаг --delete и исключение storage/app/ — пара, существующая
   только вместе (см. §23 и §32);

5. production-команды: storage:link (идемпотентно);
6. миграции: php artisan migrate --force;
7. очистка/пересоздание cache: optimize:clear → config:cache →
   route:cache → view:cache;
8. проверить /up;
9. smoke-test.

Окно между шагом 4 (новый код на проде) и шагом 6 (миграции)
безопасно только при соблюдении §16: новый код обязан работать со
старой схемой БД (expand-contract).

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

`storage/app/` содержит пользовательские загрузки (галерея), которые
существуют только на production и никогда не синхронизируются из dev.

`storage/backups/` содержит backup'ы БД и файлов, которые существуют
только на production. При rsync --delete без этого исключения backup'ы
будут удалены.

Неполный exclude-список вместе с rsync --delete = потеря данных на
production. Исключение проверяется в review каждого изменения
deploy-скрипта.

Переносятся (сборка в dev, на проде не нужны composer и node):
vendor/, public/build/, app/, bootstrap/, config/, database/,
public/, resources/, routes/, storage/framework/ (без cache/).

Точный список фиксируется в deploy/rsync-exclude.txt.

---

# 24. ROLLBACK

## Принцип

Rollback — не просто `git checkout previous`. Термин используется для
двух разных операций:

- **code rollback** — возврат кода на предыдущий release без отката БД,
  если текущая схема БД остаётся совместимой с предыдущим кодом;
- **full rollback / restore** — возврат кода, БД и необходимых файлов
  к согласованному состоянию backup.

Безопасный rollback возможен только при выполнении условий:

1. код предыдущего релиза совместим со схемой БД либо восстанавливается
   согласованная схема;
2. инфраструктура (PHP, extensions) способна работать с этим кодом;
3. для full rollback есть полный backup, содержащий состояние, к которому
   откатываемся.

## Два сценария

### A. Rollback кода без отката БД

Применимо, если схема БД совместима (expand-contract, §16).

    git checkout <previous-tag>
    make deploy

БД остаётся текущей, новая схема поддерживает и старый код.

### B. Rollback с восстановлением БД

Применимо, если схема БД несовместима или данные повреждены.

1. Создать автоматический backup текущего production-состояния.
2. Открыть BackupPage в админке (или использовать CLI).
3. Выбрать backup только по внутреннему `backup_id`, а не по произвольному
   пути к файлу.
4. Система запускает **проверку совместимости** (§17):
   - PHP version — доступна ли;
   - Laravel version — поддерживается ли текущим PHP;
   - extensions — все ли установлены;
   - db_schema_version — совместима ли схема backup с кодом, к
     которому откатываемся;
   - hash_db — целостность дампа.
5. Если проверка прошла — maintenance mode, блокировка конкурирующих
   операций, restore БД/файлов и checkout соответствующего git tag.
6. После restore — `/up`, smoke-test и запись результата в ActivityLog.
7. Если проверка не прошла — **отказ** с записью причины в ActivityLog.
   Обход проверки не является штатным сценарием и допускается только
   по отдельной аварийной процедуре с явным подтверждением администратора.

## Паттерн восстановления на shared hosting

Restore не может быть выполнен:
- в обычном HTTP-запросе админки (таймаут PHP-FPM);
- в queue worker (max-time=55 секунд, раздел 10);
- в scheduler-задании (тот же лимит).

Единственный безопасный путь — управляемая CLI-процедура через SSH.

Поток:

1. Admin в BackupPage выбирает `backup_id`, проходит MFA и повторную
   аутентификацию.
2. BackupPage создаёт запись `restore_request`:
   - `request_id` — UUID
   - `backup_id` — внутренний ID (никогда не путь)
   - `admin_user_id`
   - `created_at`
   - `token` — HMAC-SHA256(`request_id + backup_id + secret`)
   - `status` — `pending | applying | done | failed`
   - `expires_at` — `created_at + 30 минут`
3. BackupPage показывает admin инструкцию:
   «SSH на production → `make rollback APPLY <request_id>`».
4. Admin выполняет команду по SSH. CLI:
   - проверяет token и срок действия;
   - блокирует параллельные deploy/backup/restore (file lock);
   - создаёт backup текущего состояния;
   - включает maintenance mode;
   - проверяет совместимость (§17);
   - восстанавливает БД и файлы;
   - выключает maintenance;
   - проверяет `/up`;
   - пишет результат в ActivityLog;
   - помечает request как `done` или `failed`.
5. Если token истёк или проверка не прошла — CLI отказывает без
   изменений.
6. Все шаги 4 логируются в ActivityLog и в файл `restore.log`.

Прямой вызов restore из HTTP-запроса админки запрещён (раздел 32).

## Цена

Rollback глубже одного совместимого релиза — потеря данных после
точки backup. Это фиксируется в документации как известная цена.

## CLI-эквивалент

    make rollback LIST              — список доступных backup'ов
    make rollback CHECK <id>        — проверка совместимости
    make rollback APPLY <id>        — применить (с подтверждением)
    make restore-test <id>          — восстановление в отдельном env

---

# 25. GIT

Ветки: main/master, feature/*, fix/*, upgrade/*, release/*.
Production deployment — только по release tag. SemVer: vMAJOR.MINOR.PATCH.
На каждый production release — tag. CHANGELOG.md ведётся вручную.

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
    database/
        migrations/
        seeders/
    tests/
        Feature/
        Unit/
    docker/
        nginx/
        php/
        mysql/
    deploy/
        rsync-exclude.txt
        cron.txt
        deploy.sh
        upgrade.sh
        rollback.sh
    docs/
        adr/
            upgrades/
        ai/
        design/
            wireframe.html
            admin.html
        designreview.md
        adminreview.md
    .github/
        workflows/

Структура app/Filament и других framework-specific каталогов
формируется согласно выбранной версии Filament и Laravel.

---

# 27. MAKEFILE

Makefile предназначен для WSL2/dev/CI. Команды:

    make up
    make down
    make build
    make test
    make lint
    make audit
    make deploy
    make rollback
    make backup
    make restore-test
    make upgrade-check

Команды реальны и проверены. Не создавать Makefile-команды без
реальной backend-implementation. Команды backup / rollback /
restore-test / upgrade-check подключаются на этапах 11 и 20.

---

# 28. DOCUMENTATION

README описывает: требования; запуск на чистой WSL2; Docker; создание
.env; миграции; seeders; запуск тестов; сборку frontend; deployment;
перенос на Timeweb; rollback; backup; restore-test; обновление
зависимостей. Плюс чек-листы «перед deploy» и «перед обновлением».

docs/adr/ — архитектурные решения. Каждый ADR короткий: проблема;
решение; причины; альтернативы; последствия.

docs/designreview.md, docs/adminreview.md — источники истины по
вёрстке, ролям, страницам и ресурсам админки (см. §37).

---

# 29. TIMEWEB STAGING

До завершения разработки обязательно выполняется промежуточный
перенос на поддомен Timeweb. Цель: обнаружить проблемы production
environment в середине разработки, а не в конце.

На staging проверяются: PHP, extensions, MySQL, document root, .env,
storage, queue, cron, scheduler, uploads, mail, /up, permissions,
cache, deployment, backup, restore, rollback.

Инфраструктура staging — второй каталог, вторая БД, отдельные
cron-задачи на том же аккаунте Timeweb. Фиксируется в ADR до
этапа 12; staging используется также для проверки major-обновлений
(§20).

---

# 30. ЭТАПЫ

Критерий готовности каждого этапа: make test зелёный; CI зелёный;
ручная проверка; осмысленный commit.

Порядок этапов определяется зависимостями. Этап 0 (аудит Timeweb)
обязателен, но не блокирует этапы 1–11; блокирует только этапы 12–14.

## Этап 0 — аудит Timeweb

Аудит по чек-листу §7. Развернуть чистый Laravel на поддомене,
проверить /up, проверить доступность mysqldump, tar, gzip, места на
диске. Результаты — в ADR-002.

Этап 0 блокирует этапы 12–14 и не блокирует этапы 1–11.

## Этап 1 — выбор и фиксация стека

Проверить связки: PHP 8.4 + Laravel + Filament 5 и PHP 8.4 + Laravel
+ Filament 4. Выбрать по правилу тай-брейка (§3). Создать ADR-001.

## Этап 2 — Docker dev environment

PHP, Nginx, MySQL, Node/Vite, Mailpit с healthchecks (§4).
Проверить /up. Развернуть skeleton Laravel.

## Этап 3 — Authentication и роли

- Регистрация, логин, восстановление пароля (или отключение
  регистрации с фича-флагом).
- Роли admin, editor, user. Guest — виртуальная.
- Policies и spatie/laravel-permission.
- Middleware EnsurePageAccess.
- Матрица доступа (таблица page_role_access).
- Первый администратор через production-safe механизм.

## Этап 4 — Filament: каркас админки

- AdminPanelProvider: путь /admin, dark mode, discovery resources.
- Login, MFA, базовые policies.
- UserResource, RoleResource.
- PermissionsMatrixPage.
- Дашборд с базовыми виджетами.
- SettingsPage (общие, регистрация, логирование).

## Этап 5 — Models + migrations

- News, Category, GalleryItem, Page, User.
- Comment, Event, ActivityLog, PageRoleAccess.
- Seeders (демо отдельно от production).
- SoftDeletes для User, News, Comment, Page.

## Этап 6 — Filament Resources

News, Categories, Comments, Gallery, Pages, Events, Logs.

## Этап 7 — Public part

Лента новостей, страница новости, галерея, игроки, инфо-страницы,
логин, регистрация. Blade + Tailwind + Alpine + Vite.
Фиксированный header + footer, светлая/тёмная темы.

## Этап 8 — Images

Upload, validation (MIME, size, megapixel checks), resize, WebP,
queue, failed jobs (§12).

## Этап 9 — Tests

Критичные Feature/Unit тесты (§15) на MySQL 8.4.

## Этап 10 — CI

Полная настройка: composer validate, composer audit, Pint, Larastan,
Pest на MySQL 8.4 (§21).

## Этап 11 — Backup/restore/rollback

- Backup с метаданными (§17).
- BackupPage в админке.
- Проверка совместимости.
- Restore-test.
- CLI: make backup / make restore-test / make rollback.

## Этап 12 — промежуточный Timeweb staging

Перенос проекта на поддомен по чек-листу §29.

## Этап 13 — Deployment

rsync, cron, queue, scheduler, storage, cache — по §§10, 11, 22, 23.

Устанавливаются CLI-скрипты: deploy.sh, rollback.sh, upgrade.sh.

Базовый update-workflow через CLI (`make upgrade-check`) существует с
этого этапа. UpdatePage в админке (этап 15) — только информационный
интерфейс поверх уже работающего CLI.

## Этап 14 — Production

SSL, .env, backup, monitoring, /up, permissions, cron, queue, mail,
storage, smoke-test. После проверки — production domain, первый
полный backup, репетиция rollback.

## Этап 15 — Update mechanism (UI)

Требует уже работающего CLI-workflow с этапа 13.

Добавляется:
- UpdatePage в админке — только просмотр и подготовка;
- расширенный отчёт `make upgrade-check` с сохранением в
  `docs/adr/upgrades/`;
- регламент минорных и мажорных обновлений (§20).

Кнопка «Обновить сейчас» отсутствует.

---

# 31. ИТОГОВЫЙ РЕЗУЛЬТАТ

## Development

На чистой WSL2: docker compose up -d — проект запускается и проходит
smoke-test.

## Production

Сайт работает на Timeweb без Docker, Redis, Supervisor, pcntl и
долгоживущих процессов.

## Обязательные возможности

Новости, галерея, игроки, инфо-страницы, регистрация (управляемая),
административная панель, роли, матрица доступа, MFA для admin/editor,
загрузка изображений, комментарии с модерацией, события, журнал
действий, backup с проверкой совместимости, rollback, restore-test,
механизм обновлений, scheduler, monitoring, CI, безопасные
обновления.

---

# 32. ЗАПРЕЩЕНО

- wildcard * в dependency versions;
- .env в Git;
- vendor/ в Git;
- node_modules/ в Git;
- backup-файлы в Git;
- storage-артефакты в Git;
- storage/backups/ в Git;
- composer update на production;
- deployment без зелёного CI;
- deployment без backup;
- rollback без проверки совместимости (§17);
- автообновление production из админки;
- вызов restore БД из HTTP-запроса админки (только CLI, §24);
- rsync --delete с неполным exclude-списком (в частности — без
  storage/app/ и storage/backups/);
- хардкод секретов;
- хардкод абсолютных путей Timeweb;
- SQLite как основная test DB;
- необоснованное расхождение dev/CI/prod;
- зависимость production от Docker, Redis, Supervisor, pcntl,
  долгоживущих worker-процессов;
- использование down() migration как гарантированного rollback;
- credentials в документации и generated code;
- первый production deployment без предварительного staging;
- пакет без проверки существования и поддержки;
- выдуманные команды, пакеты, API;
- placeholder вместо рабочего кода.

---

# 33. ПРИНЦИП РАБОТЫ AI

AI обязан:

1. Сначала понять текущую архитектуру.
2. Проверить существующий код.
3. Проверить текущие версии зависимостей.
4. Не ломать существующие решения без причины.
5. При обнаружении проблемы сообщить о ней.
6. Не скрывать uncertainty.
7. Не придумывать отсутствующие возможности.
8. Не обновлять dependency только ради новой версии.
9. При security update объяснять причину обновления.
10. При major update отдельно показывать breaking changes.

Если для решения задачи необходим внешний пакет, перед предложением
проверить: существование; совместимость; актуальность; поддержку;
необходимость. Задача, решаемая стандартными средствами Laravel,
утилитой flock или Artisan::call(), пакетом не закрывается.

---

# 34. ФОРМАТ РАБОТЫ

## Шаг 1

Сначала предоставить: краткое резюме архитектуры; выбранные версии;
обоснование выбора PHP/Laravel/Filament; проверку совместимости;
список основных зависимостей; структуру проекта; список создаваемых
файлов; порядок разработки; потенциальные риски.

После этого остановиться и ждать подтверждения.

## Шаг 2

После подтверждения: работать итеративно; выдавать файлы по одному;
каждый файл полностью; перед файлом — 1–2 строки о назначении; после
файла — что проверить вручную.

Стандартные файлы framework skeleton (Laravel skeleton, config/*.php,
bootstrap/*, public/index.php, artisan) создаются официальными
инструментами (composer create-project, php artisan make:*,
php artisan vendor:publish) — не вручную и не по одному файлу.
Правило «по одному файлу» применяется к авторским файлам проекта
(модели, контроллеры, Resource, миграции, ADR, docker-конфиги).

## Шаг 3

Если обнаружено: противоречие ТЗ; несовместимость; небезопасное
решение; отсутствие команды или пакета; проблема Timeweb; проблема
deployment — не продолжать молча. Сначала сообщить: проблема,
причина, последствия, предлагаемое решение — и дождаться решения,
если оно влияет на архитектуру.

---

# 35. НОВАЯ СЕССИЯ

При начале новой сессии:

1. Запросить у пользователя docs/ai/context.md и текущий статус
   репозитория (ветка, последние коммиты, выполненные этапы);
2. Определить уже выполненные этапы;
3. Не начинать проект с нуля;
4. Не переопределять ранее принятые ADR без причины.

---

# 36. ГЛАВНЫЙ ПРИНЦИП ПРОЕКТА

> **Получить безопасное, поддерживаемое и предсказуемое веб-приложение
> с длительным жизненным циклом, которое разрабатывается в
> WSL2 + Docker, а после завершения разработки переносится на
> ограниченный shared-hosting Timeweb и продолжает безопасно
> обновляться без зависимости от сложной серверной инфраструктуры.**

Версия PHP, Laravel, Filament и остальных компонентов выбирается как
средство достижения этой цели, а не как самоцель.

---

# 37. DESIGN DOCUMENTS

## Назначение

Проект сопровождается четырьмя design-документами. ТЗ **ссылается**
на них, но **не дублирует** их содержимое. При конфликте:

- по стеку, безопасности, деплою, backup, обновлениям — **приоритет
  у docs/ai/context.md**;
- по вёрстке, ролям публичного сайта, страницам, матрице доступа,
  ресурсам админки — **приоритет у design-документов**.

## Список

| Файл | Назначение |
|---|---|
| docs/designreview.md | Публичный сайт: дизайн-токены, каркас, страницы, компоненты, контракт данных |
| docs/adminreview.md | Админ-контур: роли, права, ресурсы Filament, матрица доступа, структура сайдбара, миграции, политики |
| docs/design/wireframe.html | HTML-макет публичного сайта — визуальный референс |
| docs/design/admin.html | HTML-макет админки — визуальный референс |

## Правило

Если AI-агенту нужна информация о вёрстке, страницах, ресурсах
админки, ролях, матрице доступа — он **читает design-документы**,
а не действует по памяти. Если design-документ молчит по вопросу —
вопрос поднимается пользователю до начала генерации файлов.

## Открытые вопросы

Дизайн-документы содержат раздел «Открытые вопросы» (designreview.md
§11, adminreview.md §13). До их закрытия соответствующие фрагменты
реализуются по мокам или откладываются. См. §38.

---

# 38. OPEN QUESTIONS

Открытые вопросы проекта. Закрываются через ADR или через
редактирование design-документов.

## Из designreview.md §11

1. Поля карточки игрока («Data 1 / Data 2», «Calcula / Promathia»,
   «Robots / Jumxi») — заглушки или реальные данные? Что должен
   отдавать сервер?
2. Список имён файлов в public/img_site — заполнить фактическими
   файлами из архива сайта.
3. Объём админ-контура первой фазы: только дашборд + пользователи
   + роли + матрица или сразу все ресурсы?
4. Tailwind или свои CSS-переменные? В макетах — ванильный CSS.
   Решение до переноса в Blade-шаблоны.

## Из adminreview.md §13

5. ActivityLog: spatie/laravel-activitylog или своя таблица?
6. RichEditor: TinyMCE, Tiptap или встроенный Filament RichEditor?
7. Управление заявками на события: отдельная страница или
   relation-manager?
8. Settings: spatie/laravel-settings или key-value таблица?
9. Мультиязычность в админке: нужна или только RU?
10. Хранение аватаров: public/img_site/avatars/ или
    storage/app/public/?
11. Экспорт данных (CSV, XLSX или визуальная заглушка)?

## Из ТЗ (v3.8)

12. Регистрация: временно приостановлена или отключается навсегда?
13. Механизм backup: собственный или на базе spatie/laravel-backup?
14. Объём backup первой фазы: full (БД + файлы + manifest) сразу
    с этапа 11 или начать с db-only и перейти на full к production?
    §17 фиксирует, что full — целевой формат; открыт только порядок
    внедрения.
15. Внешнее хранилище backup: S3 / restic / скачивание вручную?
16. Механизм обновлений: только CLI + админ-страница «информация»
    или ещё что-то?

До закрытия вопроса AI не должен молча выбирать архитектурное решение.
Если вопрос влияет на безопасность, данные, схему БД, зависимости или
публичный контракт — реализация останавливается и решение запрашивается
у владельца проекта. Для низкорисковых визуальных/временных деталей
допускается временная реализация по явно указанному в design-документе
макету с записью решения в CHANGELOG/ADR.

---

# ИСТОРИЯ ВЕРСИЙ

**3.8.1** — три уточнения: восстановление на shared hosting выполняется
только через CLI (паттерн с `restore_request`, §24); §38/Q14 приведён
в соответствие с §17; разграничены этапы 13 и 15 по времени появления
update-workflow.

**3.8** — финализация принятого ядра проекта и усиление production
security/recovery: зафиксированы PHP 8.4, Laravel 13.33.0, Filament 5.8.4,
Livewire 4.4.6, MySQL 8.4 и ключевые пакеты; добавлены таблицы принятого
стека, dev-инструментов, Docker и runtime; backup manifest переведён на
фактические runtime/lock-версии; `signed_by` заменён на
`created_by_user_id`; полный backup определён как БД + пользовательские
файлы + manifest; усилены restore/rollback, session/upload security,
защита destructive-действий и ActivityLog; уточнено правило закрытия
открытых вопросов.

**3.7** — интеграция design-документов (designreview.md, adminreview.md,
wireframes) в общую структуру проекта: новый §37 DESIGN DOCUMENTS
(ссылки вместо дублей), §38 OPEN QUESTIONS. Расширены: §1 (FFXI Phoenix
community portal), §13 (4 роли + матрица доступа + guest), §14
(8 Resources + 4 кастомные страницы), §15 (23 сценария), §17 (backup
с метаданными, проверка совместимости, retention 7/4/12, BackupPage),
§20 (UpdatePage, make upgrade-check), §22 (backup с метаданными), §23
(storage/backups/ в exclude), §24 (rollback через BackupPage с
проверкой совместимости), §26 (docs/design/), §27 (make backup,
rollback, restore-test, upgrade-check), §30 (переписаны 3–7, добавлен
15), §31, §32 (новые запреты: rollback без проверки, автообновление
production из админки, storage/backups/ в Git), §34 (оговорка про
skeleton).

**3.5** — уточнение раздела 30: зафиксирована зависимость этапов —
этап 0 (аудит Timeweb) блокирует этапы 12–14 и не блокирует
этапы 1–11. Ранее правило существовало только в переписке.

**3.4** — критическое исправление: storage/app/ в deploy exclude
(защита пользовательских файлов при rsync --delete; дублировано в
разделах 22 и 32); принцип переносимости (раздел 2); тай-брейк
выбора стека (раздел 3); ionCube в аудит (раздел 7); Dependabot/
Renovate и триггер EOL для major (раздел 20); окно rsync→migrate
и связка --delete + exclude (раздел 22); инфраструктура staging в
ADR (раздел 29); дедупликация: этапы 0/12/13 ссылаются на разделы
7/29/22; «запросить context.md» вместо «прочитать» (раздел 35);
code-блоки — только для команд, путей и конфигураций.

**3.3** — рецензируемая база.
