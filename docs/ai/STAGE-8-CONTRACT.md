# Stage 8 — Filament. Архитектурный контракт

> Статус: решения владельца R1–R7 зафиксированы для проектирования Stage 8.
> Этот документ не выполняет и не заменяет runtime-изменения. При расхождении
> с кодом существующее поведение не считается автоматически утверждённым
> контрактом.

## §0. Контекст и границы

Stage 8 создаёт административную панель управления уже реализованным
приложением. Текущий `/admin` — scaffold Filament; Resources и кастомные
страницы Stage 8 ещё не реализованы.

**In scope:**

- 12 Resources: User, Role, Guest, News, Comment, Gallery, Page, Event,
  EventType, Rank, DashboardWidget, ActivityLog.
- `PermissionsMatrixPage` и `SettingsPage`.
- Filament Dashboard со статистикой, графиком, audit preview и shortcuts.
- MFA через встроенный Filament AppAuthentication (TOTP), opt-in.
- IP allowlist через Settings и middleware, opt-in.
- Аудит административных действий.
- Просмотр и ручная обработка запросов пользователей.

**Out of scope:**

- `BackupPage` — Stage 9.
- `UpdatePage` — Stage 15.
- `api_chart` / `html_board` в Filament — после отдельного ADR; это не
  автоматическое продолжение публичных DashboardWidget-типов Stage 7.
- Экспорт данных и ExportAction — исключены из Stage 8; отдельный ADR после
  Stage 14.
- Автоматическое каскадное удаление пользовательского контента или PII.
- Изменение `RoleAndPermissionSeeder` как часть ограничения editor.

**Существующая база:** панель настроена в
[`AdminPanelProvider.php`](../../app/Providers/Filament/AdminPanelProvider.php);
модели, миграции, permissions и часть Policies уже существуют. Их наличие не
означает наличия Filament UI или полного административного authorization.

## §1. Решения владельца (R1–R7)

### R1. Markdown editor и единый безопасный pipeline

- Для `NewsResource.body`, `PageResource.body` и
  `EventResource.description` использовать Filament MarkdownEditor.
- Для `CommentResource.body` использовать обычный Textarea и хранить
  комментарий как plain text.
- Любой ввод, который является Markdown, должен проходить один pipeline:
  **Markdown → `ContentRenderer` → `HtmlSanitizer` → HTML**. Это относится
  к админке, кабинету пользователя и API. Отдельных admin-only путей и
  обходов sanitizer нет.
- MarkdownEditor работает в Markdown-режиме. Сгенерированный или введённый
  HTML не сохранять как доверенную разметку и не выводить напрямую.
- Если MarkdownEditor недоступен в используемой версии Filament 5.9 без
  дополнительного пакета, применить Textarea с подсказкой «Markdown».
- Comment — отдельный plain-text контракт, не Markdown. Комментарии не
  получают HTML-путь от редактора; при выводе текст остаётся экранированным.
  Если какой-либо существующий маршрут комментариев принимает Markdown,
  его поведение требует отдельной проверки и не должно создавать второй
  способ принимать доверенный HTML.

**Обоснование:** сохраняется существующая ответственность
`ContentRenderer`/`HtmlSanitizer`; RichEditor, генерирующий произвольный HTML,
не должен расходиться с Markdown-полями модели.

### R2. Editor — контентный администратор

Editor имеет доступ к:

- NewsResource — с `news.manage_site`;
- CommentResource — с `comments.moderate`;
- GalleryResource / управлению Album и Photo;
- PageResource — с `pages.manage`;
- EventResource — с `events.manage_any`.

Editor не имеет доступа к:

- UserResource (`users.manage`);
- RoleResource (`roles.manage`);
- PermissionsMatrixPage (`matrix.manage`);
- SettingsPage (`settings.manage`);
- DashboardWidgetResource (`widgets.manage`);
- RankResource (`ranks.manage`);
- EventTypeResource — только admin, несмотря на существующее общее
  `events.manage_any`;
- GuestResource (`guests.view`);
- ActivityLogResource (`audit.view`);
- MFA administration, IP allowlist, re-auth/security controls;
- запросам пользователей и действиям удаления/приостановки аккаунта.

**Принцип:** admin управляет системой, editor — содержимым сайта.
`RoleAndPermissionSeeder` не менять. Установленное для editor permission
само по себе не даёт доступа к запрещённой Resource: Policies/дополнительная
проверка роли должны enforce-ить этот scope, в частности для EventType.

### R3. Запросы пользователей без автоматического удаления контента

Пользователь инициирует через кабинет одно из действий:

1. **Приостановить аккаунт** — аккаунт получает статус `suspended`.
2. **Удалить аккаунт** — аккаунт получает статус `deletion_requested`.

Оба запроса включают причину/сообщение администратору. Admin видит список с
пользователем, действием, причиной, датой и статусом. Варианты UI —
раздел «Запросы пользователей» или фильтр/вкладка UserResource.

Admin может оставить аккаунт заблокированным, восстановить его, скрыть
контент, скрыть чат-сообщения, удалить выбранный контент или инициировать
окончательное удаление аккаунта. Контентные действия выполняются вручную и
отдельно от изменения статуса.

> **Account status ≠ Content deletion.** Статус аккаунта не определяет
> судьбу пользовательского контента. Скрытие, удаление или сохранение
> каждого вида контента — решение администратора.

Restore означает `status → active`. Если контент был отдельно скрыт,
восстановление аккаунта не отменяет это решение автоматически.

Hard delete допустим только как отдельное действие admin с re-auth и audit.
Автоматическую политику удаления/анонимизации данных не программировать.
Не реализовывать автоматически:

- замену PII на `deleted-user-{id}@invalid.local`;
- каскадный soft-delete News/Comment/Photo/Album;
- бессрочную блокировку ника;
- удаление Activity или Notifications.

После приостановки/запроса пользователю отправляется письмо с инструкцией
обратиться к администрации; admin получает уведомление о новом запросе.
Это MVP; будущая строгая политика удаления требует отдельного ADR.

### R4. Аудит без secrets и раскрытия PII

Логируются административные `create`, `update`, `delete`, `publish`,
`reject`, `ban`, `restore`, `assignRole`. Не логировать шумовые `mark-as-read`
и list-level toggle `is_active`.

В каждом Resource используется `App\Filament\Concerns\LogsAdminActivity`.
Trait связывается с lifecycle hooks Filament (`afterCreate`, `afterUpdate`,
`afterDelete`) и использует существующий `App\Services\AuditLogger`.
Нестандартные действия (publish/reject/ban/restore/assignRole) логируются
явно. Повторное логирование одного изменения lifecycle hook-ом и action-ом
не допускается.

В audit payload запрещены:

- пароли, в том числе хеши;
- email, телефон и другие PII в открытом виде;
- токены и MFA secrets/recovery codes;
- открытые IP-адреса, кроме явно необходимой security-записи.

Изменение email представляется как `email: [changed]`; смена пароля —
`password: [changed]`. Snapshot allowlist формируется явно; нельзя передавать
в audit целый model attributes массив без фильтрации. AuditLogResource —
read-only и доступен только admin.

### R5. MFA — opt-in

- Использовать встроенный Filament AppAuthentication (TOTP).
- Каждый admin/editor может включить MFA самостоятельно в профиле Filament.
- MFA не обязательна по умолчанию; не задавать обязательность через
  `required: fn ($user) => hasRole('admin')`.
- `admin_2fa_required` зарезервирован для будущего включения владельцем,
  исходное значение — `false`. Если значение включено, MFA обязательна для
  admin; editor продолжает включать её самостоятельно.
- Добавить в `users` `app_authentication_secret` и
  `app_authentication_recovery_codes` отдельной миграцией
  `0001_01_01_000023_add_app_authentication_to_users_table.php`.

### R6. IP allowlist — opt-in

- Middleware: `EnsureAdminIpAllowed`; оно защищает все маршруты панели,
  включая login и MFA flows.
- Источник — `settings.admin_ip_allowlist`, JSON array CIDR.
- Пустой список означает allow all.
- Настройка доступна через SettingsPage; включение требует re-auth и
  предупреждения «не заблокируйте себя».
- Доверие proxy headers не расширять в Stage 8: настройка trusted proxies
  относится к Stage 12 (Timeweb).

### R7. Экспорт исключён

Не создавать Filament ExportAction, экспортные кнопки или заглушки
экспорта. Вернуться к вопросу отдельным ADR после Stage 14 (production).

## §2. Порядок подэтапов

Порядок: **E1 → E2 → E7 → E3 → E4 → E5 → E6 → E8 → E9**.

- **E1** задаёт панель, доступ, навигационную основу и Dashboard.
- **E2** создаёт общественные/admin-only поверхности, в том числе безопасный
  интерфейс запросов без автоматических content side effects.
- **E7 Settings** предшествует **E3 Matrix**: гостевые флаги матрицы хранятся
  через существующий settings repository; Settings также задаёт значения
  безопасности и функциональные параметры.
- **E4/E5** реализуют основной контентный workflow и должны пользоваться
  единым pipeline.
- **E6** добавляет справочники, dashboard widget management и read-only
  activity/audit views; audit visibility остаётся admin-only.
- **E8** подключает MFA, allowlist, re-auth, обработку запросов и audit
  integration; опасные действия не открываются до этого этапа.
- **E9** проверяет весь согласованный контракт и фиксирует результат.

## §3. Общая архитектура

### 3.1. Panel configuration

Развивать существующий `AdminPanelProvider`: сохранить `/admin`,
`brandName`, dark mode, teal colors, middleware и discovery. Добавить
настроенные navigation groups в порядке: **Основное**, **Сообщество**,
**Контент**, **Система**; фиксировать `navigationSort`.

Locale интерфейса — `ru`. Все пользовательские подписи, labels,
validation errors, notifications, confirmations и flash messages должны
использовать переводные ключи, не разрозненные строки в Resource-коде.

### 3.2. Authorization

- Каждый Resource защищён соответствующей Policy и/или permission-aware
  `canViewAny`; custom Pages защищены `canAccess`.
- Скрытая navigation item — только UX, не authorization.
- Editor scope строго соответствует R2; admin-only resources должны
  возвращать отказ и при прямом URL/action invocation.
- Учитывать явные запреты Policies и безопасное поведение при отсутствующей
  Policy/permission; не вводить общий allow-all fallback.
- `EventTypeResource::canViewAny()` явно проверяет
  `auth()->user()->hasRole('admin')`. Это решение, а не риск: permission
  `events.manage_any` также назначен editor, а `RoleAndPermissionSeeder`
  менять запрещено по R2.
- `RankResource` и `DashboardWidgetResource` используют свои permissions.
  Согласно текущему `RoleAndPermissionSeeder`, `ranks.manage` и
  `widgets.manage` выданы только admin; дополнительный role gate не нужен,
  пока распределение permissions не изменится.
- Dashboard content visibility и shortcuts подчиняются тому же scope.

### 3.3. Audit (R4)

Существующая таблица `admin_audit_logs` и `AuditLogger` — базовый persistence
layer. Новый Filament concern должен передавать только allowlisted,
redacted значения. AuditLogResource только читает данные. Существующая
фиксация request IP в `AuditLogger` должна быть согласована с R4: открытый IP
допустим только для отдельно обоснованной security-записи; обычные CRUD
payload его не дублируют.

**Политика IP в audit:**

- `AuditLogger` получает параметр `includeIp: bool = false` либо отдельный
  метод `logSecurity(...)`.
- Логировать IP только в security-событиях: login, logout, failed login;
  MFA setup/challenge/recovery code use; IP allowlist change; re-auth перед
  destructive action; `assignRole`; hard delete пользователя.
- Не логировать IP в обычных CRUD: create/update/delete News, Comment, Page,
  Event, Album, Photo; обычные Settings changes; toggle `is_active`, publish,
  reject.
- `AuditLogger::log(...)` остаётся основным методом. Security-вызовы
  передают `includeIp: true`; существующие вызовы Stage 6/7 не меняются и
  используют значение по умолчанию `false`.

### 3.4. Security (R5, R6)

MFA и allowlist не обязательны по умолчанию. Включение MFA производится
пользователем в профиле; включение allowlist администратором требует
re-auth и предупреждения. Проверки должны покрывать UI и серверный путь.
Не считать наличие переключателя доказательством применения middleware.

### 3.5. Единый Markdown pipeline (R1)

Админские формы сохраняют Markdown, а не HTML. Модель/сервисный путь
формирует HTML только через `ContentRenderer` и `HtmlSanitizer`. Не добавлять
Filament-specific renderer, raw HTML bypass или отдельный sanitizer.
Plain-text комментарии остаются plain text и экранируются при выводе.

## §4. E1 — Panel + Dashboard

- **Цель:** настроить общую панель и Dashboard с реальными данными для admin
  и editor.
- **Файлы:** `app/Providers/Filament/AdminPanelProvider.php`;
  `app/Filament/Widgets/*`; `app/Filament/Pages/*` только если потребуется
  отдельная dashboard page; соответствующие Policies/concerns; Pest tests
  в `tests/Feature/Filament/*`.
- **Модели и relations:** User, News, Comment, Photo, Event, GuestVisitor,
  AdminAuditLog; использовать существующие отношения и query scopes.
- **Permissions:** `dashboard.view`; editor видит только контентные метрики,
  admin — все согласованные показатели.
- **Policy:** панель продолжает проверять `canAccessPanel`; Dashboard data и
  shortcuts ограничиваются ролью и правами.
- **UI:** users total/+week, news, pending comments, photos, events за неделю,
  deletion/suspension requests, guests online; регистрации за 30 дней,
  10 последних audit entries для admin и shortcuts.
- **Security:** не раскрывать гостевые IP/идентификаторы в dashboard;
  audit preview redacted и admin-only.
- **Tests:** ориентировочно 8–12: panel auth/role scope, real counts, date
  bounds, editor data visibility, shortcut authorization.
- **Acceptance criteria:** все значения происходят из БД; editor не видит
  системные показатели; unauthenticated/user access отказан; dashboard не
  маскирует ошибки запросов success-shaped fallback-ом.
- **Зависимости:** существующие модели, permissions и scaffold.

## §5. E2 — User + Role + Guest + Requests

- **Цель:** административное управление пользователями и ролями, просмотр
  гостей и обработка account requests без автоматического удаления контента.
- **Файлы:** `app/Filament/Resources/UserResource/*`,
  `RoleResource/*`, `GuestResource/*`; request actions/pages при выборе
  отдельного UI; policies; уведомления; migrations только для согласованных
  account/request fields; tests.
- **Модели и relations:** User, Spatie Role/Permission, GuestVisitor,
  UserRank, UserSocialLink. Использовать relations для ролей, ранга и
  соцссылок; не удалять связанные модели автоматически.
- **Permissions:** `users.manage`, `roles.manage`, `guests.view`; editor
  запрещены все три поверхности.
- **Policy:** использовать существующий `UserPolicy`, расширив при
  необходимости; Role/Guest — новые admin-only правила. Запретить
  self-delete/self-demotion согласно действующим ограничениям.
- **UI:** User list/search/filters, роли, статус, верификация, ранг;
  Role assignment; GuestVisitor list; account request table с user/action/
  reason/date/status. Actions: оставить заблокированным, restore, ручные
  content/chat moderation actions; окончательный hard delete только после
  re-auth в E8.
- **Security:** причина запроса и статус не дают права автоматически
  удалять/скрывать контент. Не показывать editor данные запросов. Все
  административные state changes аудируются по R4 после подключения E8.
- **Tests:** ориентировочно 12–18: policy matrix, request list/status,
  restore semantics, no automatic content mutation, editor denial,
  notification recipients.
- **Acceptance criteria:** `suspended` и `deletion_requested` различаются;
  restore переводит только статус в `active`; скрытие/удаление контента —
  отдельные явные действия; user/admin notifications корректны; прямой URL
  защищён.
- **Зависимости:** User lifecycle и cabinet request entry points. Фактическая
  схема сейчас содержит `deletion_requested`, но не утверждённую
  `suspended`-реализацию; её поля/миграцию нужно согласовать с существующей
  моделью без изменения RoleAndPermissionSeeder.

## §6. E7 — Settings

- **Цель:** безопасно редактировать известные настройки приложения.
- **Файлы:** `app/Filament/Pages/SettingsPage.php`,
  `app/Filament/Concerns/*` для повторно используемой валидации при
  необходимости, tests; обновление `SettingsSeeder` для `admin_2fa_required`
  default false.
- **Модели и relations:** Setting и SettingsRepository; запись набора
  значений должна очищать settings cache и быть атомарной, где это возможно.
- **Permissions:** `settings.manage`; admin-only.
- **Policy:** `canAccess`/permission check плюс server-side action check.
- **UI:** группы General, Registration, Comments, Chat, Events,
  Gallery/Profile, Security, Activity; boolean/enum/numeric/timezone/CIDR
  поля с явными границами. `admin_ip_allowlist` — JSON array CIDR; empty
  list означает allow all.
- **Security:** не редактировать произвольные неизвестные keys; allowlist
  включается с warning и re-auth; не доверять proxy headers до Stage 12.
- **Tests:** ориентировочно 10–15: validation per type, authorization,
  cache invalidation, atomic updates, empty allowlist semantics,
  re-auth requirement.
- **Acceptance criteria:** сохраняются только известные валидированные
  keys; значения читаются через SettingsRepository сразу после save;
  `admin_2fa_required` false по умолчанию; редактор не имеет доступа.
- **Зависимости:** E1, существующие Setting/SettingsRepository/Seeder;
  Settings выполняется до E3.

## §7. E3 — Permissions Matrix

- **Цель:** управлять видимостью секций сайта по ролям и для guest согласно
  ADR-007.
- **Файлы:** `app/Filament/Pages/PermissionsMatrixPage.php`,
  tests; при необходимости — общий authorization helper.
- **Модели и relations:** Spatie Role/Permission, Setting через
  SettingsRepository.
- **Permissions:** `matrix.manage`; admin-only.
- **Policy:** `canAccess` и повторная проверка при сохранении.
- **UI:** роли `user/editor/admin` × `section.*.view`; отдельно guest flags
  `guest_sections`; сохранение через `syncPermissions` и
  `SettingsRepository::setMany`.
- **Security:** не создавать `page_role_access`; валидировать известный
  набор секций, сохранять согласованно, сбрасывать permission/settings
  caches. Не допускать частичного состояния при ошибке.
- **Tests:** ориентировочно 8–12: matrix values, save/rollback, guest cache
  flush, role permission cache flush, unauthorized role/action, unknown key
  rejection.
- **Acceptance criteria:** middleware `section.access` видит новые значения
  после сохранения; источник истины совпадает с ADR-007; editor не получает
  доступа к странице.
- **Зависимости:** E1, E7, ADR-007.

## §8. E4 — News + Comment

- **Цель:** контентное создание/публикация новостей и comment moderation.
- **Файлы:** `app/Filament/Resources/NewsResource/*`,
  `CommentResource/*`; shared Markdown field config только если не дублирует
  pipeline; tests.
- **Модели и relations:** News→User, News→Comments; Comment→User,
  morph target, parent/replies.
- **Permissions:** `news.manage_site`, `news.moderate`,
  `comments.moderate`; scope редактора дополнительно фиксируется R2.
- **Policy:** использовать NewsPolicy и CommentPolicy; проверить list query,
  record actions, bulk actions и прямой вызов.
- **UI:** News title/slug/excerpt/body Markdown/cover/scope/status/
  published_at/is_pinned/comments_enabled; filters по scope/status/author;
  preview, publish, reject с обязательной причиной, archive, bulk actions.
  Comments — Textarea/plain text, контекст target, author, status/reported;
  approve/reject/spam, delete/hide, фильтры pending/reported.
- **Security:** MarkdownEditor хранит Markdown; generated HTML только через
  renderer/sanitizer. Comment body не становится доверенным HTML. Audit
  publish/reject и другие действия согласно R4.
- **Tests:** ориентировочно 14–20: pipeline round-trip/sanitization,
  Markdown persistence, comment escaping, moderation status transitions,
  role/action permission, filters and bulk-action auth.
- **Acceptance criteria:** сохранённый body соответствует текущим Markdown
  полям; public HTML формируется безопасным renderer; editor не обходит
  Policy; причины reject обязательны; exports отсутствуют.
- **Зависимости:** E1, единый content pipeline; audit hooks доводятся в E8.

## §9. E5 — Gallery + Page + Event

- **Цель:** администрировать Albums/Photos, статические Pages и Events.
- **Файлы:** `app/Filament/Resources/GalleryResource/*` (Albums и Photos
  relation manager; не создавать дублирующий второй resource без
  необходимости), `PageResource/*`, `EventResource/*`; tests.
- **Модели и relations:** Album→Photos/coverPhoto/User; Page; Event→type,
  participants, comments, leader.
- **Permissions:** `albums.manage_site`, `photos.delete_any`,
  `pages.manage`, `events.manage_any`. Editor доступен к контентным
  Resources по R2.
- **Policy:** использовать AlbumPolicy, PhotoPolicy, PagePolicy,
  EventPolicy; проверить, что admin actions и editorial forms не обходят
  field/model constraints.
- **UI:** gallery album metadata, cover, scope, order, published; photo
  batch upload, captions, reorder, publish/delete. Page title/slug/body
  Markdown/published/menu/meta. Event title/type/description Markdown/
  location/start/duration/capacity/registration close/status; relation view
  participants/comments.
- **Security:** использовать существующий image processing/storage path;
  Markdown Event/Page через единый renderer/sanitizer; не переписывать
  participant бизнес-правила Filament-specific обходом.
- **Tests:** ориентировочно 16–22: upload validation, policy matrix,
  Markdown output, page publication/menu, event relations and state
  transitions, bulk action authorization.
- **Acceptance criteria:** только валидные изображения сохраняются
  обработанными; редакторские изменения безопасны; связанные участники
  отображаются без несанкционированного изменения; все mutation actions
  разрешены Policy.
- **Зависимости:** E1, image/content services; audit hooks — E8.

## §10. E6 — EventType + Rank + Widget + ActivityLog

- **Цель:** настроить справочники/публичные dashboard widgets и предоставить
  защищённый просмотр activity/audit.
- **Файлы:** `EventTypeResource/*`, `RankResource/*`,
  `DashboardWidgetResource/*`, `ActivityLogResource/*`; Policies и tests.
- **Модели и relations:** EventType→Events, UserRank→Users,
  DashboardWidget, AdminAuditLog, Activity.
- **Permissions:** `events.manage_any` для типа события с дополнительной
  admin-only проверкой; `ranks.manage`, `widgets.manage`, `audit.view`.
- **Policy:** новые policies для EventType, UserRank, DashboardWidget,
  ActivityLog. ActivityLogResource admin-only, read-only.
- **UI:** CRUD и reorder справочников; DashboardWidget key/title/type/span/
  active/order/settings; audit/activity filters по actor/action/subject/date
  и read-only detail.
- **Security:** editor запрещён EventType, Rank, Widget, AuditLog. Не
  включать секреты/PII в view; не трактовать публичные widget records как
  Filament widget implementation.
- **Tests:** ориентировочно 10–16: role checks, readonly audit, widget
  validation/reorder, existing public dashboard behavior unaffected.
- **Acceptance criteria:** все actions authorization-protected; audit
  нельзя менять/удалять из UI; управление типами событий только admin;
  Stage 7 public dashboard сохраняет поведение.
- **Зависимости:** E1; audit ingestion/filters интегрируются в E8.

## §11. E8 — MFA + IP allowlist + Audit integration

- **Цель:** подключить согласованные security controls, аудитировать
  admin mutations и завершить ручной account-request workflow.
- **Файлы:** `app/Providers/Filament/AdminPanelProvider.php`, `User` model,
  migration `0001_01_01_000023_add_app_authentication_to_users_table.php`,
  `app/Http/Middleware/EnsureAdminIpAllowed.php`,
  `app/Filament/Concerns/LogsAdminActivity.php`, Resource hooks/actions,
  возможно re-auth action/service; tests.
- **Модели и relations:** User MFA fields; Setting для allowlist/2FA flag;
  AuditLogger/AdminAuditLog; request/account status fields по R3.
- **Permissions:** MFA self-service для panel users по R5; allowlist и
  account hard delete только admin; audit recording привязан к
  административному actor.
- **Policy:** role-specific checks для security настройки; запрет editor на
  allowlist, MFA administration и account requests. MFA self-service в
  профиле разрешён самому пользователю.
- **UI:** setup/disable TOTP и recovery codes в профиле; Settings allowlist с
  CIDR validation, warning и re-auth; re-auth-gated account hard delete;
  restore/content/chat moderation actions. Никакого авто-каскада.
- **Security:** allowlist middleware покрывает все panel routes; empty list
  allow-all. MFA secrets и recovery codes не включать в audit. Использовать
  cache backend с atomic locks для предотвращения повторного принятия TOTP,
  согласно Filament docs. Hard delete логируется без payload с PII;
  Content deletion выполняется отдельно.
- **Tests:** ориентировочно 16–24: opt-in MFA lifecycle/recovery, role
  behavior, allowlist IPv4/IPv6/CIDR and all routes, empty list semantics,
  re-auth failures, audit redaction/action uniqueness, no automatic
  content/activity/notification deletion.
- **Acceptance criteria:** MFA не обязательна при default settings; MFA
  доступна admin/editor self-service; allowlist выключен пустым списком;
  изменения admin действий логируются ровно один раз redacted; все
  необратимые действия запрашивают re-auth; контент меняется только по
  отдельному ручному действию.
- **Зависимости:** E2, E6, E7; настроенный Filament panel и Settings.

**Delete flow уточнение:** это не каскадная процедура. Администратор вручную
выбирает каждое действие над account/content; hard delete не запускает
предопределённое уничтожение PII, News, Comments, Photos, Albums, Activities
или Notifications.

## §12. E9 — Acceptance

- **Цель:** доказать Stage 8 contract acceptance на уровне Pest,
  authorization, регрессии и документированных результатов.
- **Файлы:** feature tests в `tests/Feature/Filament/*`, unit tests для
  markdown/audit/security helpers, `docs/acceptance/stage-8.md`; обновление
  проектного статуса только по фактическому результату.
- **Модели и relations:** все ресурсы Stage 8, settings, roles, audit,
  requests.
- **Permissions:** полная матрица admin/editor/user/guest для страниц,
  records, bulk actions и прямых endpoints.
- **Policy:** ни одна UI-кнопка не считается единственной проверкой доступа.
- **UI:** пройти dashboard, каждую Resource, matrix/settings, MFA, allowlist,
  notifications, validation и destructive confirmations.
- **Security:** audit redaction, sanitizer pipeline, CSRF/auth, re-auth,
  CIDR/IP behavior, editor isolation, отсутствие export UI.
- **Tests:** ориентировочно 20–30 acceptance scenarios сверх тестов E1–E8;
  полный запуск существующего Pest suite перед приёмкой. Точное количество
  определяется покрытием, не является числовым DoD.
- **Acceptance criteria:** тесты green; Pint/Larastan clean; `git diff
  --check`; ручной UI review; все R1–R7 проверены по runtime; `stage-8.md`
  описывает только фактически проверенное; в отчёте перечислены gaps.
- **Зависимости:** завершены E1–E8.

## §13. Cross-cutting

- **Translation keys:** locale `ru`; labels, actions, statuses, validation,
  notices и confirmations через локализацию проекта.
- **Markdown storage:** `body`/`description` хранят Markdown; `body_html`
  производный, санитизированный HTML там, где поле существует. Не хранить
  непроверенный RichEditor HTML как готовое содержимое. Comment хранится и
  отображается plain text.
- **Filament notifications:** использовать для UI feedback и admin alerts;
  пользовательские email и admin notification о запросах остаются
  серверными notification workflows. Не считать UI toast заменой
  уведомления.
- **Flash messages:** подтверждать успешные действия только после успешного
  завершения persistence/action; ошибки показывать явно.
- **File uploads:** использовать существующее public storage/image processing,
  ограничения размера/типа и безопасное удаление файлов отдельно от
  account status. Не принимать произвольные пути из формы.
- **Экспорт:** явно исключён R7; не добавлять action, button, endpoint,
  package/configuration или placeholder.
- **Policies:** menu hiding не заменяет server-side authorization; действия
  и bulk actions проверяют права отдельно.

## §14. Risks

1. **RichEditor и Markdown:** случайное сохранение HTML или пропуск
   `ContentRenderer` нарушит безопасный pipeline и может изменить публичный
   рендер.
2. **Comment plain text:** текущие способы отображения должны оставаться
   escaped; comment не должен превращаться в канал для доверенного HTML.
3. **Editor permissions:** `events.manage_any` назначен editor в
   seeder. EventTypeResource имеет дополнительный admin-only gate в §3.2.
   Seeder не меняется. Проверить в E6, что editor не проходит
   `canViewAny()` для EventType.
4. **Account requests:** текущая модель содержит deletion request, но не
   утверждённый suspended workflow. Request metadata и UI нельзя
   реализовывать так, чтобы статус автоматически удалял/скрывал контент.
5. **Audit:** существующий AuditLogger получает request IP; это должно
   соответствовать ограничению R4 и не раскрывать IP в обычном payload.
   Lifecycle hooks и custom actions могут случайно записать событие дважды.
6. **MFA:** secret/recovery migrations, opt-in поведение и cache atomic-lock
   requirement; failure не должен запирать пользователя без recovery flow.
7. **IP allowlist:** proxy trust не входит в Stage 8; невалидное или
   неверно интерпретированное CIDR-значение может заблокировать admin.
   Изменение allowlist требует re-auth и предупреждения.
8. **Hard delete:** FK cascade может удалить/повредить связи вопреки R3.
   Нельзя использовать автоматический каскад как реализацию пользовательской
   политики.
9. **Dashboard counts:** запросы с soft deletes/status filters должны
   соответствовать определениям метрик, а не только считать все строки.
10. **Stage boundaries:** `api_chart`/`html_board`, BackupPage и UpdatePage
    не должны незаметно проникнуть в Stage 8.

## §15. Testing strategy

1. **Policy matrix:** admin/editor/user/guest; visibility и action permission
   тестировать отдельно, в том числе прямые URL/bulk actions.
2. **CRUD and validation:** required/unique/enum/range/foreign key
   constraints для каждой Resource.
3. **Content safety:** Markdown persist → renderer → sanitizer → rendered
   output; попытки HTML/script/unsafe URI; Comment plain-text escaping.
4. **Audit:** событие на каждое согласованное изменение; отсутствие
   duplicate rows; исключённые шумовые события; redaction email/password/
   phone/token/MFA; read-only admin-only ActivityLog.
5. **Security:** MFA opt-in, recovery behavior, allowlist empty/CIDR/malformed,
   application route coverage, re-auth for security/destructive actions.
6. **Request workflow:** suspended/deletion_requested states, reason and
   notifications, restore only status, content unchanged until separate
   manual action.
7. **Regression:** существующие публичные pages, Stage 7 dashboard/chat/
   events/activity, account flow и `section.access`/ADR-007 сохраняют
   поведение.
8. **Quality gates:** targeted tests во время подэтапов; перед acceptance —
   полный Pest, Pint, Larastan и `git diff --check`. Приёмочный отчёт
   содержит реальные команды и результаты.

## §16. Открытые вопросы

R1–R7 закрывают решения владельца. Следующие **детали реализации и схемы**
нужно проверить/зафиксировать до соответствующих изменений, не меняя
утверждённое поведение:

1. R1 задаёт единый pipeline для любого Markdown и отдельно фиксирует
   Comment как plain text. В Stage 8 комментарий не является Markdown и
   выводится escaped; существующие comment entry points проверить на
   фактическое хранение/рендер, не расширяя формат.
2. R3 требует статусы `suspended` и `deletion_requested`. Текущая
   схема `users` содержит только `deletion_requested` +
   `deletion_requested_at` + `deletion_reason`. Для `suspended`
   требуется миграция:
   - `0001_01_01_000024_add_suspended_fields_to_users_table.php`
     (номер 000024; 000023 зарезервирован под MFA по R5).
   - Поля: `suspended_at` (timestamp null), `suspension_reason`
     (varchar 500 null).
   - Возможен отдельный case в Enum UserStatus: `Suspended`.
   - Миграция входит в E2 (перед созданием UserResource UI для
     обработки запросов).
   - Если будет решено использовать отдельную таблицу
     `account_requests` — это отдельный ADR до E2. По умолчанию
     используем существующую модель `users` + новые поля.
3. R5 резервирует migration `000023` для MFA. Если suspension/request
   metadata требует отдельной миграции, использовать указанный для неё
   номер `000024`, не переиспользуя `000023`.
4. R6 задаёт JSON array CIDR и пустой список как allow-all, но не задаёт
   точные ошибки валидации и поведение при malformed IP/CIDR. Выбрать
   строгую server-side проверку до включения; не доверять proxy headers до
   Stage 12.

Ранее открытые вопросы экспорта закрыты R7 (исключён), выбор внешнего
RichEditor закрыт R1, а мультиязычность не входит в данный контракт: locale
панели — `ru`.

## §17. Definition of Done Stage 8

- [ ] Реализованы все 12 согласованных Resources и две custom Pages.
- [ ] Dashboard показывает реальные, проверенные метрики и разделяет
  editor/admin данные.
- [ ] Sidebar имеет четыре согласованные группы, порядок и корректные
  permissions.
- [ ] Policies защищают Resources, Pages, actions, bulk actions и прямые
  запросы; editor ограничен R2 без изменения RoleAndPermissionSeeder.
- [ ] Markdown-контент проходит единый ContentRenderer/HtmlSanitizer
  pipeline; Comment остаётся plain text и выводится escaped.
- [ ] Settings и Permissions Matrix используют существующие источники
  данных и корректно инвалидируют caches.
- [ ] Account request workflow соответствует R3: status не запускает
  автоматическое изменение контента.
- [ ] MFA self-service доступна, но не обязательна по умолчанию; allowlist
  выключен пустым списком и защищает все panel routes при включении.
- [ ] Audit actions реализованы без дубликатов и без раскрытия запрещённых
  secrets/PII; ActivityLogResource read-only/admin-only.
- [ ] ExportAction/кнопки экспорта отсутствуют; BackupPage — Stage 9,
  UpdatePage — Stage 15, `api_chart`/`html_board` не включены.
- [ ] Pest, Pint, Larastan и `git diff --check` проходят; ручная проверка
  выполнена; `docs/acceptance/stage-8.md` отражает проверенные результаты и
  оставшиеся gaps.
