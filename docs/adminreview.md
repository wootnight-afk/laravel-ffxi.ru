1. Назначение
Административная панель сообщества FFXI Phoenix — отдельный контур, изолированный от публичного сайта. Задача панели: управление контентом и пользователями, которые видны на публичной части, плюс системные настройки и логирование.

Ключевые принципы:

Отдельная визуальная среда (не путать с публичным сайтом).

Отдельный стек (Filament 5.8 + Livewire 4.4).

Отдельные роли и права (spatie/laravel-permission).

Публичный сайт не знает о существовании админки, кроме ссылки в шапке для админов.

Вся логика доступа — через Policies + матрицу доступа (см. §4).

Референс: admin.html (визуальный макет в стилистике Filament).

2. Стек — админ-специфичный
Из общего стека проекта (🧩 Ядро стека.txt) админка использует:

Компонент	Версия	Роль в админке
PHP	~8.4.0	runtime
Laravel Framework	^13.33	каркас
Filament	^5.8	ядро админки
Livewire	^4.4	реактивность Filament
spatie/laravel-permission	^8.3	роли и права
intervention/image	^4.3	обработка загружаемых изображений (галерея, аватары)
MySQL	8.4	хранение
Запрещено (см. 🧩 Ядро стека.txt):

bezhansalu/filament-shield — не используем; права через нативные Policies + spatie/laravel-permission.

laravel/fortify — не в стеке.

Redis / Memcached / SQLite.

3. Роли и права
3.1 Роли
Фиксированный набор (создаются сидером, нельзя удалять через UI):

Роль	Slug	Описание
Администратор	admin	Полный доступ ко всему
Редактор контента	editor	Управление контентом (новости, галерея, страницы, события)
Пользователь	user	Авторизованный игрок публичного сайта
Гость	guest	Без авторизации (виртуальная роль, не в БД)
guest — виртуальная роль, не хранится в roles. Используется только в матрице доступа для обозначения «без авторизации».

3.2 Пакет
spatie/laravel-permission ^8.3 — единственный источник истины по ролям и разрешениям.

Разрешения (permissions) именуются по схеме:

text
{resource}.{action}
Примеры:

text
news.viewAny, news.view, news.create, news.update, news.delete, news.publish
gallery.upload, gallery.delete
pages.manage-access
users.assignRole
settings.manage
4. Матрица доступа
Сводная таблица прав. Источник истины по тому, кто какие страницы публичного сайта видит.

Страница	URL	Гость	User	Editor	Admin
Главная	/	✓	✓	✓	✓
Новости	/news	✓	✓	✓	✓
Галерея	/gallery	✓	✓	✓	✓
Контакты	/contacts	✓	✓	✓	✓
Игроки + чат	/players	—	✓	✓	✓
Карточка игрока	/players/{id}	—	✓	✓	✓
События	/events	✓	✓	✓	✓
4.1 Хранение
Реализуется таблицей page_role_access:

text
id                bigint unsigned PK
page_slug         varchar(64)          -- 'players', 'players.{id}', ...
url_pattern       varchar(191)         -- '/players/{id}' для отображения
role_slug         varchar(32)          -- 'guest', 'user', 'editor', 'admin'
created_at        timestamp
updated_at        timestamp

UNIQUE (page_slug, role_slug)
INDEX (page_slug)
page_slug — технический ключ (используется в middleware), url_pattern — только для отображения в UI.

4.2 Middleware
Кастомный EnsurePageAccess:

Читает page_slug из route name или из параметра page.access:players.

Проверяет наличие role_slug для текущей роли пользователя (или guest).

При отсутствии — 403 с редиректом на /login для гостей.

Кэширование матрицы — через CACHE_STORE=file (Redis запрещён). Инвалидация — при сохранении формы матрицы.

4.3 Filament-страница «Матрица доступа»
Кастомная PermissionsMatrixPage:

Таблица «Страница × Роль» с чекбоксами.

Кнопки «Выбрать всё / Снять всё».

Валидация: страница должна иметь хотя бы одну разрешённую роль (иначе сайт отдаст 403 всем).

Сохранение одной транзакцией.

5. Структура сайдбара (Filament navigation)
text
ОСНОВНОЕ
  ◧ Дашборд          [часы: UTC + устройство (Intl/cookie tz) + регион (users.secondary_timezone) (L6.4)]

СООБЩЕСТВО
  👥 Пользователи          [badge: кол-во]
  🛡 Роли и права
  🔑 Матрица доступа

КОНТЕНТ
  🏠 Главная           [L6.2: RU/EN контент]
  📰 Новости                [badge: кол-во]
  💬 Комментарии            [badge: кол-во на модерации, danger]
  🖼 Галерея
  📄 Страницы          [L6.2: RU/EN контент]
  📋 События / Формы
  🔗 Header-меню       [L6.1: CMS-навигация RU/EN]
  📌 Footer-меню       [L6.1: CMS-навигация RU/EN]

СИСТЕМА
  📜 Журнал событий
  ⚙ Настройки
5.1 Правила навигации
Группы: Основное, Сообщество, Контент, Система.

Бейджи — опциональны, отображают счётчики (кол-во пользователей, новостей, комментариев на модерации).

Скрытие пунктов по правам: пользователь без news.viewAny не видит «Новости» в сайдбаре.

Иконки — Heroicons (штатный набор Filament).

Порядок (navigationSort):

Дашборд — 0

Пользователи — 10, Роли — 11, Матрица — 12

Новости — 20, Комментарии — 21, Галерея — 22, Страницы — 23, События — 24

Главная — первым пунктом группы «Контент» (navigationSort 0 в коде, выше
«Новости»).

Журнал — 30, Настройки — 31

6. Filament Resources
Resource	Модель	Ключевые поля	Кастом
UserResource	User	name, email, roles, status, avatar, locale, timezone	смена ролей inline, блокировка
RoleResource	Role	name, slug, permissions	только для admin
NewsResource	News	title, slug, date, image, body, status	preview, публикация/снятие, редакционная дата (метка, UTC-pin)
CommentResource	Comment	text, author, target, status	модерация bulk-действиями
GalleryResource	GalleryItem	image, caption, section, date	загрузка через intervention/image
PageResource	Page	title, slug, content, is_published, roles	доступ настраивается через чекбоксы (см. §7)
EventResource	Event	title, type, starts_at, timezone, status, submissions	просмотр заявок; пояс ввода + UTC-хранение
ActivityLogResource	ActivityLog	user, action, target, ip, timestamp	read-only, фильтры; время всегда UTC
Регистрируются через php artisan make:filament-resource {Model} --generate.

6.1 Не Filament Resources, а кастомные страницы
Матрица доступа — PermissionsMatrixPage (см. §4.3).

Дашборд — стандартный Filament Dashboard с виджетами (Stats + Chart).

Настройки — SettingsPage с формами (общие, регистрация, логирование).

Страницы — единый редактор `SitePageEditor`
(`/admin/pages/create`, `/admin/pages/{record}/edit`): одна форма для CMS
(`cms-{id}`) и системных страниц (`sys.home`, `sys.news.index`,
`sys.gallery.index`, `sys.contacts`, `sys.events.index`,
`sys.events.create`). Секции: содержание (RU/EN), slug, меню, SEO (RU/EN),
изображения, компоненты; неприменимые секции отключены. CMS-страницы
доступны editor, системные — только admin. Детали — `frontend-spec.md` §7.3,
ключи — §6.15 и §6.4.2.

**L6.1 — Редакторы Header и Footer (CMS-инструменты).** Кастомные страницы
в группе «Контент»: `HeaderPage` (`/admin/header`) и `FooterPage`
(`/admin/footer`). Перевод существующих текстов Header/Footer выполнен в
L5.1; L6.1 создаёт инструменты управления: RU/EN подписи пунктов меню,
порядок, видимость, привязка к системным маршрутам или CMS-страницам,
необязательные изображения. Системные элементы Header (язык, тема,
activity bell, профиль, вход/выход) не управляются CMS. Поля Footer: RU/EN
текст, произвольные ссылки (подписи по локалям), порядок, видимость,
привязка к маршрутам/CMS-страницам. Адаптивность: `flex-wrap` для
`.footer-links`, динамическая высота футера. Хранение — `settings` (JSON)
или отдельная таблица (решение §38 Q10).

**L6.4 — Мировое время Dashboard.** `AdminDashboard` (группа «Основное», sort 0)
показывает три независимых циферблата в одной группе: **UTC** (всегда
`UTC+00:00`), **устройство** (браузерный `Intl.DateTimeFormat().resolvedOptions().timeZone`,
серверный fallback — cookie `tz` через `TimezoneResolver::browserTimezone()`,
затем `UTC`; `users.timezone` не участвует) и **регион**
(`users.secondary_timezone`). Виджет — `ClockWidget` (Filament widget),
выводится **один раз** через `discoverWidgets()` в основной сетке Dashboard
(`getHeaderWidgets()` не используется, иначе блок дублировался). Внутри —
inline-селект выбора/сброса региона: весь каталог IANA (419 зон, 11 групп),
`optionsLimit` = полный каталог (дефолтные 50 обрезали список до первых
африканских зон), серверный поиск по идентификатору и городу.
`users.secondary_timezone = NULL` — третий циферблат показывает placeholder
`--:--:--` и подсказку выбора. Смещения пересчитываются на каждом тике
Alpine (DST). `localStorage` не является основным хранилищем.

**Часовые пояса (ADR-011 §2.9).** В админке включается `FilamentTimezone`
(пояс администратора): пользовательские timestamp-колонки и `DateTimePicker`
показывают время в его поясе. Календарные метки и системные журналы
закрепляются на UTC явным `->timezone('UTC')`: `news.published_at` (поле и
колонка), `photos.taken_at`, `admin_audit_logs.created_at`, backup-журнал;
виджет последних аудит-записей — через `<x-utc-datetime>`. Включение
`FilamentTimezone` и все pin-правки выполняются одним коммитом — иначе
промежуточное состояние сдвигает метки и аудит. `settings.timezone_display`
в определении пользовательского времени не участвует (legacy).

7. Форма создания/редактирования страницы
Ключевое место админки. Реализуется как кастомная форма внутри PageResource.

7.1 Блок «Основная информация»
title — text, required

slug — text, required, unique, автогенерация из title

meta_description — textarea, max 255

content — RichEditor (TinyMCE или встроенный Filament)

7.2 Блок «Права доступа»
Четыре карточки-чекбокса: Гость / User / Editor / Admin.

Реализация:

CheckboxList в Filament со стилизованными карточками (custom view).

Синхронизация с таблицей page_role_access через afterSave hook.

Кнопки «Разрешить всем / Запретить всем» — Alpine-экшены поверх формы.

Валидация: минимум одна роль должна быть выбрана.

Логика: Admin всегда включён и недоступен для снятия (disabled + checked) — защита от блокировки сайта.

7.3 Блок «Публикация»
Тумблеры (Toggle):

is_published — опубликовать

show_in_menu — показывать в главном меню

is_indexable — индексировать поисковиками

7.4 Список страниц
Таблица (PageResource list) со столбцом «Доступ»:

Отображает сводку ролей: Все роли, User + Editor + Admin, Только Admin.

Read-only badge, вычисляется из page_role_access.

8. Policies
Для каждой модели — своя Policy в app/Policies/.

Общий паттерн:

php
public function viewAny(User $user): bool
{
    return $user->can('news.viewAny');
}

public function update(User $user, News $news): bool
{
    return $user->can('news.update')
        && ($user->hasRole('admin') || $news->author_id === $user->id);
}
Особые случаи:

PagePolicy::viewAny — разрешено только editor и admin (для управления).

RoleResource — доступен только admin (RolePolicy).

PermissionsMatrixPage::canAccess — только admin.

ActivityLogResource — read-only, доступен admin и editor (просмотр).

9. Миграции
Помимо штатных users, roles, permissions — добавить:

create_page_role_access_table — матрица доступа (§4.1).

create_activity_log_table — журнал событий (собственные таблицы `activities` + `admin_audit_logs`, не spatie/activitylog).

Изменения в users: status (enum: active/pending/blocked), avatar_path, last_login_at, last_login_ip.

create_news_table, create_comments_table, create_gallery_items_table, create_pages_table, create_events_table — по мере подключения ресурсов.

Решение по activitylog:
Собственные таблицы `activities` и `admin_audit_logs` (решение context.md §38). spatie/laravel-activitylog **не подключается**.

10. Токены и тема админки
Админка не обязана совпадать с публичным сайтом по цветам — это отдельный контур. В макете admin.html используется:

10.1 Светлая тема
Токен	Значение
--primary-500	#14b8a6
--primary-600	#0d9488
--primary-700	#0f766e
--gray-50	#f9fafb
--gray-200	#e5e7eb
--gray-900	#111827
10.2 Тёмная тема
Filament 5.8 поддерживает dark mode из коробки: ->darkMode() в AdminPanelProvider.

10.3 Акцент
Акцент админки — бирюзовый (#14b8a6), совпадает с публичным сайтом. Это осознанное решение: переключаясь между сайтом и админкой, пользователь видит единый «бренд».

10.4 Шрифт
По умолчанию Filament использует Inter (или системный). Дополнительных подключений не требуется.

11. Правила и запреты
Filament Shield не используется. Права — только через нативные Policies + spatie/laravel-permission.

Никакого laravel/fortify. Авторизация — через Filament Login или стандартный Laravel auth.

Ресурсы регистрируются через AdminPanelProvider->discoverResources() — без ручного перечисления.

Все формы — Filament Forms, без самописных Blade-форм.

Все таблицы — Filament Tables, без самописных <table>.

Действия администратора логируются через `admin_audit_logs` (собственная таблица).

Права проверяются и в UI, и в Policy. Скрытие пункта меню — не защита, а UX.

Мягкое удаление (SoftDeletes) для User, News, Comment, Page — на случай восстановления.

Матрица доступа кэшируется, инвалидация — при сохранении.

Admin не может удалить сам себя и не может снять с себя роль admin через UI.

Все загружаемые изображения обрабатываются через intervention/image (ресайз, thumbs).

**Время в админке (ADR-011).** Пользовательские моменты — в поясе
администратора; аудит (`admin_audit_logs`), backup-журнал и системные
события — всегда UTC с пометкой `(UTC)`. `app.timezone = UTC` неизменен;
глобальный `date_default_timezone_set()` запрещён. Календарные метки
(`news.published_at` при `scope=site`, `photos.taken_at`) не
конвертируются. Подробности — `frontend-spec.md` §6.16.

**Переключатель языка в Filament (ADR-011 §2.24).** Расположение:
непосредственно слева от профиля пользователя (`PanelsRenderHook::USER_MENU_BEFORE`).
Точка вставки в vendor: `vendor/filament/filament/resources/views/components/user-menu.blade.php:43`
— хук выводится непосредственно перед `<x-filament::dropdown>` (триггер-аватар).
Регистрация через `Panel::renderHook()` в `AdminPanelProvider` или через
`FilamentView::registerRenderHook()`. Переключатель — POST-форма без JS,
использует `route('locale.update')`, `LocaleResolver`, `users.locale`.
Язык публичной части и админ-панели согласованно определяется предпочтением
пользователя. Навигационные группы (`NavigationGroup::make(__('filament.navigation.*'))`)
должны резолвиться лениво (на рендере), чтобы смена локали администратора
не нарушала сопоставление групп с ресурсами.

**RU/EN CMS (ADR-011 §2.20–2.21).** CMS-страницы (`Page`), главная
(`sys.home`), страница новостей (`sys.news.index`) и остальные системные
страницы единого редактора — поддерживают RU/EN контент:
`title`/`title_en`, `body`/`body_en`, `meta_title`/`meta_title_en`,
`meta_description`/`meta_description_en` для `Page`; ключи `home.*`/`home.*_en`,
`news_page.*`/`news_page.*_en` для `settings`. Fallback по отдельным полям:
страница без EN-версии остаётся в английском меню; EN-заголовок с RU-текстом
при отсутствии `body_en`. Данные другого языка не теряются при переключении
в редакторе. SEO-поля — по локалям для CMS-страниц и Home/News.

12. Связь с публичным сайтом
Публичный сайт читает данные, созданные в админке (новости, галерея, страницы).

Публичный сайт не вызывает сервисы админки напрямую — только через модели.

Ссылка «Администрирование» в шапке публичного сайта видна только admin (согласно §4 designreview.md).

Матрица доступа управляет публичными страницами. Внутренние страницы админки регулируются Policies, не матрицей.

13. Открытые вопросы
~~Библиотека для журнала событий: spatie/laravel-activitylog или своя таблица?~~ — **закрыто** (context.md §38): используются собственные таблицы `activities` и `admin_audit_logs`; spatie/activitylog не подключается.

RichEditor: TinyMCE, Tiptap, или встроенный Filament RichEditor? Зависит от требований к верстке контента.

Управление заявками на события: отдельная страница EventSubmissionsPage или relation-manager внутри EventResource?

Настройки сайта: Filament-плагин spatie/laravel-settings или своя settings таблица ключ-значение? Рекомендация — spatie/laravel-settings.

Объём первой фазы: только дашборд + пользователи + роли + матрица? Или сразу все ресурсы?

Мультиязычность: решено — ADR-011. Интерфейс RU/EN, язык хранится в
`users.locale`, часовой пояс — независимо в `users.timezone`. Админка
использует тот же `LocaleResolver` (fallback `ru`). Языковые URL-префиксы
не вводятся. **ADR-011 §2.16–2.25 (2026-11-06):** CMS-контент по локалям
(§2.20–2.21), Header/Footer редакторы (§2.22–2.23), Filament language
switcher (§2.24), Dashboard clocks (§2.25).

Хранение аватаров: public/img_site/avatars/ (как публичные ассеты) или storage/app/public/?

Экспорт данных (кнопки «Экспорт» в макете) — CSV, XLSX, или только визуальная заглушка?

**L6.1 (Header/Footer редакторы):** Хранение — **закрыто** (ADR-011 §8.2):
`settings`, JSON-массив `header.items` / `footer.items` + `footer.text_ru` /
`footer.text_en`. Отдельная таблица не создаётся.

**L6.2 (RU/EN CMS):** **Закрыто** (ADR-011 §8.2): Home/News — плоские ключи
`settings` с суффиксом `_en` (`home.h1_en`, `home.body_en`,
`home.meta_title_en`, `news_page.*_en`); `pages` — nullable-колонки
`title_en`, `body_en`, `body_html_en`, `meta_title_en`, `meta_description_en`.
Изображения общие для RU/EN.

**L6.4 (Dashboard часы):** **Закрыто** (ADR-011 §8.2): `users.secondary_timezone`
(миграция L6.4, применена) — третьи часы (регион); обновление виджета — Alpine.js
`setInterval`, первый рендер серверный (без Livewire polling); смещения
пересчитываются динамически (DST); выбор региона — inline в виджете (419 IANA-зон,
optgroup по 11 регионам, поиск, кэш `TimezoneOptions` 1 час).

14. Результат фазы (Definition of Done)
Для текущего этапа админки:

□ AdminPanelProvider настроен: path /admin, dark mode, discovery resources.
□ Сидер ролей (admin, editor, user) + базовые разрешения.
□ UserResource с CRUD + назначение ролей.
□ RoleResource с CRUD + управление permissions (только admin).
□ PermissionsMatrixPage — работает, сохраняет, кэширует.
□ EnsurePageAccess middleware — работает для публичных маршрутов.
□ Дашборд с 4 stats-виджетами + Chart.
□ Пункты сайдбара скрываются по правам.
□ Activity log подключён (собственные таблицы `activities` + `admin_audit_logs`).
□ **L5.7:** Интерфейс админки полностью на выбранном языке (RU/EN); навигационные группы корректно отображаются при смене локали.
□ **L6.3:** Переключатель языка в Filament (`USER_MENU_BEFORE`, слева от профиля); смена языка не нарушает навигацию.
□ **L6.1:** Редакторы Header/Footer в группе «Контент»; системные элементы Header не затронуты; Footer не переполняется.
□ **L6.2:** RU/EN CMS для Home/News/Pages; fallback по полям; страница без EN остаётся в меню.
☑ **L6.4:** Dashboard часы: 3 циферблата (UTC / устройство / регион), Alpine `setInterval`, inline-селект региона, динамическое смещение (DST), `TimezoneResolver::browserTimezone()`.
15. Ссылки
Публичный сайт → docs/designreview.md

Публичный макет → designreview.html

Админ-макет → admin.html

Стек → 🧩 Ядро стека.txt

Скриншоты → docs/design/*.png

Правило последней инстанции: если вопрос касается публичного сайта — ответ в designreview.md. Если касается управления, прав, ресурсов Filament — ответ здесь. Если ответа нет ни там, ни здесь — вопрос в §13 и адресуется заказчику.
