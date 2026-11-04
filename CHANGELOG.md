# Changelog

Все значимые изменения проекта. Формат — по мотивам
[Keep a Changelog](https://keepachangelog.com/ru/1.1.0/), версии — SemVer
(context.md §25). Файл ведётся вручную.

## [Unreleased]

### News — редактор шапки страницы `/news`

- **Админка:** новая страница `App\Filament\Pages\NewsPage`
  (`/admin/news-page`), в сайдбаре скрыта (`shouldRegisterNavigation()` =
  false). Точка входа — header action «Редактор страницы» в `ListNews`, рядом
  с «Создать»; для editor кнопка скрыта, потому что страница admin-only.
- **Хранение:** `settings` через `SettingsRepository`, без миграций и
  моделей. Ключи `news_page.meta_title`, `news_page.h1`, `news_page.body`,
  `news_page.main_image`, `news_page.main_image_width`,
  `news_page.main_image_height`; отсутствие ключа или `null` = дефолт
  (`Новости игры`, `img/ogp_eu.webp`, 1200×630, два прежних абзаца).
- **`App\Services\NewsPageContent`** (новый) — `defaults()`, `editable()`,
  `view()`. Текст второго абзаца по умолчанию собран через одинарные
  переносы: пустая строка в Markdown начала бы новый абзац, а на странице
  это один `<p>` с мягкими переносами (визуально — как раньше).
- **`HomeImageUploader`:** добавлен слот `news_page`; конфигурация слотов
  сведена в карту `dir`/`suffix`/`max`, поэтому файл шапки попадает в
  `storage/app/public/news/header/{hash}_header.webp` (WebP q85,
  `scaleDown` до 1920×1080, без апскейла, ≤ 4 МБ).
- **Форма:** Title, H1, Картинка шапки (`FileUpload` + тумблер «вернуть
  стандартную»), Текст (`MarkdownEditor`). Сохранение пишет
  `admin_audit_logs` (`news_page.updated`, old/new, без IP).
- **Фронт:** `NewsController::index()` передаёт `newsPage`, шапка
  `news/index.blade.php` выводится из настроек; классы (`page-title`,
  `home-image`, `content-text`, `divider`), размеры (1200×630 по умолчанию) и
  порядок блоков сохранены. Цикл новостей и пагинация не тронуты.
- **Docs:** `frontend-spec.md` 1.8 — §6.4.2, §7.3, §9 (сценарий 30);
  `adminreview.md` §6.1.
- Проверено: `php -l`, `git diff --check`; `/news` при дефолтах —
  whitespace-нормализованный текст шапки совпадает с прежним, 5 карточек и
  пагинация идентичны; сохранение 1600×900 → WebP 1600×900 в
  `news/header/`, отдача HTTP 200 `image/webp`; сброс удаляет файл и
  возвращает `img/ogp_eu.webp` 1200×630.

### Home — редактор Главной страницы (singleton)

- **Админка:** новая страница `App\Filament\Pages\HomePage` (`/admin/home`,
  группа «Контент», `navigationSort = 0` — выше «Новости»). Filament 5 не
  поддерживает singleton-ресурсы, поэтому страница кастомная: без списка и без
  записи, сразу форма. Доступ — только `admin` (`canAccess()`), editor пункт
  не видит.
- **Хранение:** существующая таблица `settings` через `SettingsRepository`,
  без миграций и новых моделей. Ключи: `home.meta_title`, `home.h1`,
  `home.body`, `home.hero_logo`, `home.main_image`,
  `home.main_image_width`, `home.main_image_height`. Отсутствие ключа или
  `null` = встроенный дефолт.
- **`App\Services\HomePageContent`** (новый) — единый источник правды:
  `defaults()`, `editable()`, `view()`. Дефолты повторяют прежнюю статичную
  страницу (`img/bx_ffxi_logo.webp`, `img/ffxi.webp`, 592×333, два абзаца).
- **`App\Services\HomeImageUploader`** (новый) — по образцу
  `NewsCoverUploader`: слоты `hero`/`image`, проверка по фактическому MIME
  (JPEG/PNG/WebP), ≤ 4 МБ, ≤ 25 Мп, короткая сторона ≥ 100 px, всегда
  пере-кодирование в WebP q85, `scaleDown` без апскейла, случайное имя
  `{random12}-{sha1[:8]}_{slot}.webp` в `storage/app/public/home/{slot}/`,
  возврат `{path, width, height}`.
- **Форма:** Title, H1, Hero-логотип, Основное изображение, Текст
  (`MarkdownEditor`). Тумблеры «вернуть стандартное» видны только при
  загруженном файле, удаляют файл и сбрасывают ключи в `null`. Сохранение
  пишет `admin_audit_logs` (`home.updated`, old/new, без IP). Ошибка загрузки
  отменяет сохранение целиком.
- **Фронт:** `home.blade.php` переведён на `HomePageContent::view()`;
  структура и классы (`hero hero--single`, `logo-wrap`, `page-title sr-only`,
  `home-image`, `content-text`) сохранены. Для стандартного логотипа разметка
  не изменилась; для загруженного добавляется `logo-wrap--custom` и
  нейтральные правила в `app.css` (`aspect-ratio: auto`, `position: static`,
  `width: 100%`), чтобы CSS-кроп дефолтного логотипа не применялся к
  произвольному файлу. Текст выводится через `ContentRenderer`
  (CommonMark + `HtmlSanitizer`); пустой текст скрывает блок.
- **Docs:** `frontend-spec.md` 1.7 — §6.15, §7.3, §9 (сценарий 29);
  `adminreview.md` §5 — пункт «Главная» в группе «Контент».
- Проверено: `php -l`, `git diff --check`; `/` при дефолтах байт-в-байт
  повторяет прежний hero-блок; загрузка 1600×900 → 1287×724 WebP, баннер
  415×100 без апскейла, 800×450 → `width="800" height="450"`; GIF/SVG/60×60/
  14.5 МБ отклонены; тумблеры сброса удаляют файлы и возвращают дефолт.

### News — обложки новостей (загрузка, хранение, вывод)

- **Первопричина бага «обложка не отображается»:** `NewsCoverUploader::MIN_SIDE
  = 200` отклонял широкие баннеры. Файл `22822.jpg` имеет размер **415×100**,
  поэтому `validate()` бросал `ImageProcessingException::tooSmall`, обложка не
  сохранялась, а новость создавалась с `cover_path = NULL` (в UI оставалось
  только уведомление). Порог снижен до 100 px — как в `AvatarUploader` и
  `ImageProcessor` (там 50 px).
- **Без апскейла:** если источник меньше целевого кадра 1280×720, применяется
  `scaleDown()` (пропорции сохраняются) вместо `cover()`. Баннер 415×100
  остаётся 415×100 вместо 7-кратного растягивания с кадрированием.
- **Порядок на детальной странице:** `news/show.blade.php` — заголовок →
  обложка → метаданные (автор, дата) → текст. Раньше `<img>` выводился **до**
  `<h1>`, затем метаданные стояли выше обложки.
- **Лента:** обложка убрана из `news/index.blade.php` — выводится только на
  детальной странице.
- **CSS:** добавлено правило `.news-image` (его не существовало вовсе) —
  `display: block; max-width: 100%; height: auto; border-radius: 4px`
  (маленький баннер не растягивается выше натурального размера).
- **Админка:** `FileUpload` для обложки ограничен
  `acceptedFileTypes(['image/jpeg','image/png','image/webp'])` — UI совпадает
  с `NewsCoverUploader::ALLOWED_MIMES`.
- **Docs:** `frontend-spec.md` 1.6 — новый §6.4.1 «Обложки новостей»
  (форматы, лимиты, хранение, публичный URL, место вывода, чек-лист
  диагностики), §6.4 и §7.3 приведены в соответствие.
- Проверено: `22822.jpg` → `news/covers/3/…_cover.webp`, HTTP 200
  `image/webp`; PNG и WebP загружаются и отдаются; в ленте обложек нет.

### News — редакционная дата `published_at`

- **Семантика:** `published_at` — редакционная дата (дата анонса
  официального источника), а не момент выхода новости на сайте. Дата может
  быть в будущем и в прошлом; на видимость не влияет. Видимость определяется
  только `status = published` + `published_at IS NOT NULL`. Отложенной
  публикации по расписанию нет и не будет.
- **Модель:** `News::isPublished()` и `scopePublished()` больше не проверяют
  «дата не в будущем» (`isPast()` / `published_at <= now()` удалены).
  `scopePinnedFirst()` дополнен tiebreaker'ом `->orderByDesc('id')` —
  порядок при равных датах детерминирован.
- **Админка:** `DateTimePicker` для `published_at` получил `->native(false)`
  (нативный `<input type="datetime-local">` не позволял сохранить только
  дату — браузер блокировал отправку по `validity.badInput`),
  `->displayFormat('d.m.Y H:i')` и условную обязательность при
  `status = published` с сообщением «Укажите дату новости».
- **Новый сервис:** `app/Services/NewsPublishedAtResolver.php` — только дата
  → `00:00:00`, при наличии новостей на эту дату → `max(published_at) +
  5 минут`, точное время сохраняется как есть, при редактировании без
  изменения даты значение не сдвигается. Вызывается из
  `CreateNews::mutateFormDataBeforeCreate()` и
  `EditNews::mutateFormDataBeforeSave()`.
- **Запрет `now()` при создании:** автоподстановка текущего времени удалена
  из обеих страниц формы. Кнопки «Опубликовать» и «Массовая публикация»
  по-прежнему используют `now()` — это ручное действие «опубликовать прямо
  сейчас».
- **Фронтенд:** лента `/news` и страница `/news/{slug}` показывают только
  дату `d.m.Y`; время `H:i` на публичном сайте новостей не выводится.
- **Docs:** `frontend-spec.md` 1.5 (§5.4, §6.4, §7.3, §9.28, §10),
  `context.md` 4.0.5 (§11), `adminreview.md` §6.
- **TODO (вне скоупа):** кабинет (`my-news.blade.php` — `H:i`,
  `MyNewsController` — `now()`), таймзона `app.timezone = UTC`.
- Тесты не запускались и не добавлялись — отдельный этап.

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
