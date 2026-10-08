# Функциональная спецификация: FFXI Phoenix community portal

**Версия:** 1.0 (FINAL)
**Назначение:** функционал публичной части и взаимодействия.
**Файл:** docs/ai/frontend-spec.md
**Статус:** рабочий документ.

Дополняет `docs/ai/context.md` (технический контекст).
При конфликте: техническая часть (стек, безопасность, деплой, backup)
приоритетна у context.md; функционал (роли, маршруты, модели,
бизнес-логика, приёмки) — у этого файла.

---

# 0. КАК РАБОТАТЬ С ДОКУМЕНТОМ

1. Работать этапами (`context.md` §30). Перед этапом — перечитать
   относящиеся разделы.
2. Приоритет при конфликте: безопасность → context.md → эта спека →
   удобство реализации.
3. Стек фиксирован (`context.md` §3). Пакеты вне списка — через
   вопрос.
4. Каждая фича = миграция + модель + Policy + Livewire/Blade +
   интеграция в админку + тесты.
5. Продуктовые решения §2 — не обсуждать.
6. «No security by UI»: UI → Filament authorization → Policy →
   серверная проверка → БД.

---

# 1. ПРОДУКТОВЫЕ РЕШЕНИЯ (зафиксировано)

| # | Решение |
|---|---|
| 1 | Профиль по умолчанию **закрыт**, открывает сам игрок |
| 2 | **Кабинет** (управление) и **Страница игрока** (публичное представление) — раздельные пространства |
| 3 | Модерация player-новостей — режимы post / pre, переключает админ, default: post |
| 4 | Markdown разрешён + обязательная серверная санитизация |
| 5 | Телефон скрыт по умолчанию |
| 6 | Чат, закрытый профиль: только текстовый ник — без аватара, ссылки, бейджа |
| 7 | Регистрация — toggle админа, default: **закрыта** (до публикации политики ПД) |
| 8 | Удаление аккаунта: запрос юзера → блокировка → финальное удаление только админом (с re-auth); ник зарезервирован навсегда |
| 9 | Гость профили не видит; на гостевых страницах — контент, одобренные комментарии, фото, **текстовый ник** (без аватара/ссылки/бейджа) |
| 10 | Ранги игроков: админ настраивает список (значок + оценка), назначает вручную; бейдж виден авторизованным |
| 11 | Социальные ссылки: отдельная таблица, 12 платформ, видимость по ссылке + приватность профиля |
| 12 | Ник — case-insensitive unique на уровне БД |
| 13 | Типы событий — расширяемый справочник (админ CRUD) |
| 14 | Технический статус: email_verified_at + status enum; публично — только ранг |
| 15 | Cookie — информирование (без «принять/отклонить»); внешние аналитики отсутствуют |
| 16 | ПД — обязательное отдельное согласие при регистрации + версия политики |

---

# 2. РЕФЕРЕНСЫ И UX-ПАТТЕРНЫ

| Источник | Что заимствуем |
|---|---|
| FFXIV Lodestone | Структура страницы игрока: аватар, биография, личные новости, скриншоты |
| Steam Community | Галерея, activity feed, события с RSVP |
| Discord | UX чата (история, «↓ новые», антифлуд), роли-бейджи |
| YouTube / Steam | Комментарии: плоские + 1 уровень ответов, правка 5 минут |
| MMO-Champion / Wowhead | Лента новостей: обложка, анонс, «Читать далее», закреплённые |
| GitHub | «Опасная зона» в кабинете; re-auth для destructive |
| ❌ Reddit (анти-кейс) | Ветвящиеся треды — не берём |

---

# 3. РОЛИ, ПРАВА, ПРИВАТНОСТЬ

## 3.1 Роли

| Роль | Кто | Возможности |
|---|---|---|
| **Guest** | Неавторизованный | Гостевые страницы (по матрице 3.2): контент, одобренные комментарии, фото, текстовые ники. Трекается как `guest001…` — только для админки. Профили, чат, дашборд, 🔔 — недоступны |
| **User** | Зарегистрированный, email подтверждён | Всё гостевое + дашборд (чат/события/лента), свои новости и альбомы, соцсети, комментарии, запись на события, 🔔, кабинет, ранг-бейдж у других |
| **Editor** | User + контент-админка | В админке — только «Контент»: Новости, Комментарии, Галерея, Страницы, События |
| **Admin** | Полный доступ | Всё + пользователи, роли, матрица, справочники, настройки, виджеты, аудит, гости, очередь удалений. Усиленный вход (MFA — ADR-009) |

**MFA (ADR-009, unified site-wide).** `mfa_global_enabled` (default true) —
глобальный gate. user/editor — opt-in; admin — opt-in при `admin_2fa_required=false`,
обязан при `admin_2fa_required=true` (только когда global gate включён). Единый
challenge `/mfa/challenge`; verification — до logout; escape-hatches —
`/cabinet/security` и `/admin/settings`. Enforcement-матрица по маршрутам —
ADR-009 §2.3. Административный MFA reset — permission `mfa.manage` (admin-only,
см. §3.3).

**Неподтвердивший email**: логин и чтение — да; write-действия — 403
(middleware `verified` + Policies). Баннер «Подтвердите email».

## 3.2 Матрица доступа (baseline; редактируется админом)

| Раздел / URL | Guest | User | Editor | Admin |
|---|---|---|---|---|
| Главная `/` | ✅ | ✅ | ✅ | ✅ |
| Новости `/news`, `/news/{slug}` | ✅ | ✅ | ✅ | ✅ |
| Галерея `/gallery…` | ✅ | ✅ | ✅ | ✅ |
| Статические страницы `/{slug}` | ✅ | ✅ | ✅ | ✅ |
| События `/events` (просмотр) | ✅ | ✅ | ✅ | ✅ |
| Запись на событие | ❌ | ✅ | ✅ | ✅ |
| Игроки + Дашборд + чат `/players` | ❌ | ✅ | ✅ | ✅ |
| Страница игрока `/players/{nick}` | ❌ | ✅ | ✅ | ✅ |
| Лента активности `/activity` | ❌ | ✅ | ✅ | ✅ |
| Кабинет `/cabinet…` | ❌ | ✅ | ✅ | ✅ |
| Админка `/admin` | ❌ | ❌ | только «Контент» | всё |

**Реализация:** guest-доступ — JSON по секциям в `settings`
(`guest_sections`); роли — spatie `section.{key}.view`. Middleware
`section.access:{key}`: auth → разрешение роли; guest → settings.
`Gate::before` для admin возвращает `true` только для abilities
с префиксом `section.`. Для остальных abilities действуют обычные
Spatie permissions и Policies; явные запреты Policies сохраняются.
Изменения мгновенны (flush cache).

## 3.3 Гранулярные разрешения (spatie)

- `section.{home|news|gallery|contacts|events|players|player_profiles}.view`
- Новости: `news.manage_site`, `news.create_own`, `news.edit_own`,
  `news.delete_own`, `news.edit_any`, `news.delete_any`, `news.moderate`
- Галерея: `albums.manage_site`, `albums.create_own`, `albums.edit_own`,
  `albums.delete_own`, `photos.upload_own`, `photos.edit_own`,
  `photos.delete_any`
- Комментарии: `comments.create`, `comments.moderate`
- Чат: `chat.participate`, `chat.moderate`
- События: `events.create`, `events.join`, `events.manage_any`
- Прочее: `pages.manage`, `dashboard.view`, `profile.edit_own`,
  `panel.access`, `users.manage`, `roles.manage`, `matrix.manage`,
  `settings.manage`, `widgets.manage`, `audit.view`, `guests.view`,
  `ranks.manage`, `mfa.manage`

> **`mfa.manage` (ADR-009 §2.11).** Назначается **только** роли `admin`.
> Существует **исключительно** для административного MFA reset; других
> применений нет. Self-service MFA (`/cabinet/security`) этим permission **не**
> защищается. Reset-all не реализуется. `editor` MFA reset недоступен.

## 3.4 `<x-user-identity>` — единая точка рендера

Единственное место рендера ника/аватара/бейджа.

| Зритель | Профиль | Ник | Аватар | Бейдж ранга |
|---|---|---|---|---|
| Гость | любой | текст | ❌ | ❌ |
| Auth | открыт | ссылка | ✅ | ✅ |
| Auth | закрыт, контекст default | текст | ✅ | ✅ |
| Auth | закрыт, контекст **chat** | текст | ❌ | ❌ |
| Владелец / Admin | — | ссылка | ✅ | ✅ |

Fallback аватара: инициал + цвет из хэша ника.
Ссылки на недоступное не рендерятся (ни скрытых href).

## 3.5 Кабинет ≠ Страница игрока

| | **Кабинет** `/cabinet` | **Страница игрока** `/players/{nickname}` |
|---|---|---|
| Назначение | Управление | Публичное представление |
| Кто видит | Только владелец | Auth (матрица + приватность) |
| Состав | Профиль (аватар, раса, main job, легенда, телефон+видимость, `is_profile_public`) • Соцсети • Мои новости • Мои альбомы • Мои события • Безопасность • Опасная зона | Аватар, ник, ранг-бейдж, раса, main job, онлайн, регистрация, легенда, соцсети (если открыт), вкладки Новости/Фото, счётчики |

- Меню — «Мой кабинет», не «Мой профиль».
- Ранг в кабинете — read-only.
- «Предпросмотр» — вид как у других.

## 3.6 Правила видимости для гостей

1. Гость не имеет доступа: `/players*`, `/activity`, `/cabinet*`, чат,
   админка → 404/редирект.
2. На гостевых страницах: контент + одобренные комментарии + фото +
   текстовый ник. Без аватаров, ссылок, бейджей.
3. События: организатор — текст; участники — количеством.
4. Формы → CTA «Войдите, чтобы…».
5. Site-новости: автор = «Редакция FFXI.ru». Player-новости гостю
   недоступны (404).

---

# 4. КАРТА МАРШРУТОВ

| Method | URI | Middleware | Описание |
|---|---|---|---|
| GET | `/` | section:home | Главная: приветствие + 3 свежие новости + 6 свежих фото + 3 ближайших события |
| GET/POST | `/register` | guest, registration.open | Регистрация |
| GET/POST | `/login`, `/logout`, `/password/*`, `/email/*` | guest / auth | Auth-набор Laravel |
| GET | `/news` | section:news | Лента, пагинация 10 |
| GET | `/news/{slug}` | section:news | Полная новость |
| GET | `/gallery` | section:gallery | Список альбомов |
| GET | `/gallery/{album:slug}` | section:gallery | Сетка фото, «Показать ещё» |
| GET | `/gallery/{album:slug}/{photo}` | section:gallery | Фото + комментарии |
| GET | `/events` (+ `?type=key`) | section:events | Список, фильтр по типу |
| GET | `/events/{event}` | section:events | Карточка + участники + обсуждение |
| GET | `/{page:slug}` | section:contacts | Статические страницы |
| GET | `/players` | auth, section:players | Дашборд сообщества |
| GET | `/players/directory` | auth, section:players | Каталог игроков |
| GET | `/players/{user:nickname}` | auth, section:player_profiles | Страница игрока |
| GET | `/activity` | auth | Полная лента |
| GET | `/cabinet/{tab}` | auth | Кабинет (write — `verified`) |
| GET/POST | `/mfa/challenge` | auth | Единый MFA-challenge (`mfa.challenge` / `mfa.challenge.verify`; ADR-009) |
| GET | `/cookie` | — | Cookie-политика |
| GET | `/privacy` | — | Политика ПД |
| — | `/admin/{...}` | auth, panel.access | Filament |

**Blacklist slug:** admin, guest, login, register, directory, news,
gallery, events, cabinet, activity, api, system, cookie, privacy.

**ADR-заметка:** в MVP URL игрока = ник (неизменяем). При смене ника —
immutable `slug` + 301.

---

# 5. МОДЕЛЬ ДАННЫХ

> Владелец — `user_id`/`owner_user_id`. Перед update/delete/publish —
> проверка ownership через Policy.

## 5.1 `users` (расширение)

Стандартные: `id, name (ник), email (unique), email_verified_at,
password, remember_token, timestamps, softDeletes`.
Ник — unique, collation case-insensitive.

Новые поля:

| Поле | Тип | Примечание |
|---|---|---|
| `rank_id` | FK null → user_ranks | nullOnDelete |
| `phone` | string null | encrypted |
| `phone_is_public` | bool default false | |
| `avatar_path` | string null | |
| `is_profile_public` | bool default **false** | |
| `race` | enum null | hume, elvaan, tarutaru, mithra, galka |
| `main_job` | string null | «PLD», «RDM»… |
| `legend` | text null (Markdown, ≤4000) | |
| `legend_html` | text null | кэш |
| `last_seen_at` | timestamp null | Touch ≤1/мин |
| `last_activity_seen_at` | timestamp null | счётчик 🔔 |
| `chat_banned_until` | timestamp null | мут |
| `banned_until` | timestamp null | |
| `ban_reason` | varchar(200) null | |
| `status` | enum('active','deletion_requested') default 'active' | |
| `deletion_requested_at`, `deletion_reason` | timestamp / varchar(500) null | |
| `pd_consent_at` | timestamp null | согласие ПД |
| `pd_policy_version` | varchar(20) null | версия политики |
| `marketing_consent_at` | timestamp null | рассылка |

## 5.2 `user_ranks`

`id, key unique, title varchar(50), icon varchar(16) (emoji),
rating smallint, color varchar(20) null, sort_order, is_active bool,
timestamps`.

## 5.3 `user_social_links`

`id, user_id FK cascade, type varchar(30), label varchar(50) null,
username varchar(100) null, url varchar(500), is_visible bool default
false, sort_order, timestamps`.

Платформы (`config/social.php`, расширяемо): Discord, Telegram, VK,
Steam, Twitch, YouTube, GitHub, X, PlayStation Network, Xbox, Reddit,
Other. Каждая: title, icon, `url_template` nullable (например,
`https://t.me/{username}`).

## 5.4 `news`

`id, user_id FK, scope enum('site','player'), title varchar(150),
slug unique, excerpt text null (автогенерация 300 симв.),
body mediumtext (Markdown), body_html mediumtext null,
cover_path null, is_pinned bool, comments_enabled bool default true,
status enum('draft','pending','published','rejected','archived'),
rejection_reason varchar(300) null, published_at null, views uint,
timestamps, softDeletes`. Индекс `(scope, status, published_at desc)`.

- `scope=site` — только `news.manage_site`; `scope=player` — verified
  user для себя. `/news` — только site.
- `archived` — вне ленты/sitemap/главной; прямой URL — с плашкой.
- `pending` — только player-новости при премодерации; `rejected` — с
  причиной.

## 5.5 `albums`, `photos`

**albums:** `id, user_id FK null (null = site), scope enum('site','player'),
title varchar(120), slug unique, description text null, cover_photo_id null,
sort_order, is_published, timestamps, softDeletes`.

**photos:** `id, album_id FK cascade, user_id FK, path_original,
path_medium, path_thumb, caption varchar(200) null, taken_at null (EXIF),
exif json null (камера + дата), width, height, size_bytes, sort_order,
is_published, timestamps, softDeletes`. Индекс `album_id`.

Site-альбомы при сидировании: «Linkshell members», «Memories», «Areas».

## 5.6 `comments` (полиморфные)

`id, user_id FK, commentable_type, commentable_id, parent_id null FK
(только на корневой — 1 уровень), body text (20–2000),
status enum('approved','pending','rejected','spam'), is_reported bool,
edited_at, timestamps, softDeletes`. Индекс
`(commentable_type, commentable_id, status, created_at)`.

Применяются к News, Photo, Event. `spam` — классификация модератора,
скрыт ото всех, кроме админки.

## 5.7 `event_types`, `events`, `event_participants`

**event_types:** `id, key unique, title varchar(50), icon varchar(16),
sort_order, is_active, timestamps`. Сидируются: Party, Raid, Mission,
Quest, Farm, Помощь новичкам, Screenshot, Community, Announcement,
Recruiting, Другое.

**events:** `id, user_id FK (лидер), type_id FK null, title varchar(150),
description text (Markdown), location varchar(120), starts_at datetime,
duration_minutes null, max_participants smallint null,
registration_close timestamp null,
status enum('planned','completed','cancelled'), timestamps, softDeletes`.
Индексы: `starts_at`, `type_id`.

**event_participants:** `id, event_id FK, user_id FK, jobs varchar(100)
null («PLD/NIN»), note varchar(200) null, status enum('joined','left'),
joined_at, left_at null, unique(event_id, user_id)`. Лидер — auto.

**Бизнес-правила:** запись — при `status=planned` И `now < starts_at`
И (`registration_close` null ИЛИ `now < registration_close`) И не full.
Выход — всегда. Повторная запись — новой строкой. Уведомления: join/
leave → лидеру; отмена → участникам.

## 5.8 `chat_messages`

`id, user_id FK, body varchar(500), is_deleted bool, deleted_by_user_id
null, deleted_reason varchar(100) null, edited_at null, timestamps`.
Индекс `created_at desc`. Future-proofing: закомментированный
`guest_visitor_id` + ADR о гостевом чате.

## 5.9 `activities`

`id, actor_id FK, type string (registered, chat_message,
news_published, photo_published, event_created, event_joined,
comment_created), subject_type null, subject_id null, data json,
created_at`. Индекс `created_at desc`. Пишется listener'ами/domain
events. Для чата — queued.

## 5.10 `guest_visitors`

`id, uuid unique (cookie httpOnly, **30 дней**), display_name
('guest'+pad(id,3)), ip_hash char(64), user_agent varchar(255),
first_seen_at, last_seen_at, hits int, converted_user_id FK null,
converted_at null, timestamps`. Индекс `last_seen_at`.

## 5.11 `dashboard_widgets`

`id, key unique, title, type enum('community_chat','events_board',
'activity_feed','online_users','api_chart','html_board'), sort_order,
column_span tinyint (1–3), is_active, settings json, timestamps`.

## 5.12 Прочее

- `settings (key PK, value json)` — включая `guest_sections`;
- `pages (title, slug, body, body_html, is_published, show_in_menu,
  menu_order, meta_title, meta_description, timestamps, softDeletes)`;
- `admin_audit_logs (user_id, action, subject_type, subject_id, old json,
  new json, ip, user_agent, created_at)`;
- стандартные Laravel + spatie.

## 5.13 Seeders

Роли+разрешения; настройки; 3 site-альбома; ранги (Новичок 🌱 10,
Искатель приключений 🗡 30, Ветеран 🛡 60, Легенда Phoenix 👑 90);
типы событий; admin; демо editor; демо-пользователи (Jumxi, Oleg,
Drakonus, Monarch, Scaevola, Bugor) с контентом; демо-соцссылки;
дефолтные виджеты (chat span 2, events span 1, activity span 2,
online span 1); страница «Контакты»; страницы `/cookie`, `/privacy`
(placeholder до текстов от юриста).

---

# 6. ФУНКЦИОНАЛЬНАЯ СПЕЦИФИКАЦИЯ

## 6.1 Регистрация и аккаунт

Форма: ник, email, пароль+подтверждение, телефон (опц.), **2 чекбокса**
(ПД — обязат., рассылка — опц.).

**Ник:** 3–24, `[a-zA-Z][a-zA-Z0-9_-]*`, blacklist резервных слов,
case-insensitive unique (БД + валидация). Live-проверка (debounce 500мс):
«свободен»/«занят» + 3 кликабельные подсказки `ник{2–4 цифры}` —
`NicknameSuggester` (проверка на сервере, включая CI).

**Анти-enumeration:** «Неверный логин или пароль»; reset/resend — единый
нейтральный ответ; duplicate email — стандартная ошибка.

**Верификация:** MustVerifyEmail. Один email = один аккаунт = один ник.
Телефон — encrypted, только админам.

**При регистрации:** `pd_consent_at` = now, `pd_policy_version` = текущая.
При смене политики — повторное согласие при следующем входе.

## 6.2 Кабинет

Вкладки: **Профиль** (аватар с кроп-превью 6.6; раса; main job; легенда
Markdown с предпросмотром; телефон+видимость; `is_profile_public` с
подписью) • **Социальные сети** (6.14) • **Новости** • **Фото** •
**События** • **Безопасность** (пароль; смена email с реверификацией;
MFA: setup/verify/disable/recovery codes — ADR-009, форма Blade + POST) •
**Опасная зона** (6.12). Ранг — read-only. Формы — Livewire.

> **MFA (ADR-009).** `/cabinet/security` — основное self-service место MFA и
> обязательный escape-hatch: доступен независимо от MFA-verification. Для
> sensitive operations (disable MFA, regeneration/reissue recovery codes)
> применяется повторная аутентификация. Storage — `users.app_authentication_secret`
> / `users.app_authentication_recovery_codes`.

## 6.3 Страница игрока и каталог

**`/players/{nickname}`:** шапка — аватар (128/256 WebP), ник,
ранг-бейдж (значок + название + оценка), раса, main job, «в сети»
(<5 мин), дата регистрации, бейдж «Профиль закрыт». Легенда
(`legend_html`). Соцсети — только если открыт. Вкладки Новости/Фото.
Владельцу — «Редактировать» + «Предпросмотр».

**`/players/directory`:** карточки (аватар 80px, ник, «Ник — раса»,
статы: Main Job / Ранг / Новостей / Фото / Событий / Регистрация),
поиск, пагинация 24.

## 6.4 Новости

**Лента `/news`:** обложка (lazy, aspect-ratio), заголовок, анонс, дата,
счётчик комментариев, «Читать дальше →». Закреплённые — сверху.
Пагинация 10.

**Полная:** обложка, заголовок, `body_html`, блок автора (site —
«Редакция FFXI.ru» + дата; player — `<x-user-identity>` + плашка
«Дневник игрока»), просмотры, «Поделиться», комментарии (если enabled).

**Модерация player-новостей** (`player_news_moderation` post/pre,
default post):

- **post:** мгновенно; модератор может снять → rejected + причина.
- **pre:** → pending (виден автору с бейджем и админам; 404 другим);
  одобрение/отклонение с причиной + уведомление; бейдж «На модерации: N».
- Переключение не ретроактивно.
- Draft → published; отложенная публикация; «В архив» (editor/admin для
  site).

**SEO:** meta, OG (og:image = обложка), canonical, `/sitemap.xml`
(published site-новости + альбомы + страницы + открытые профили;
archived исключён).

## 6.5 Комментарии

- Под новостью/фото/событием; Livewire; плоские + 1 уровень ответов;
  20–2000; правка 5 мин; удаление своего; автолинковка; escaping.
- Модерация (`comments_moderation` none/reputation/all, default
  reputation): reputation — первые 5 pending. Отклонённый виден автору.
  **Spam** — скрыт ото всех, кроме админки.
- «Пожаловаться» → `is_reported`.
- Гости: видят одобренные (автор — текст). Форма → CTA.
- Сортировка старые→новые; пагинация 20; `comments_enabled=false` —
  секции нет, POST отклонён Policy.

## 6.6 Галерея

- `/gallery` — сетка альбомов. `/gallery/{album}` — сетка превью (thumb
  480, aspect 1:1/4:3/16:9), «Показать ещё» (24/стр).
- `/gallery/{album}/{photo}` — medium 1280, клик → PhotoSwipe, подпись,
  дата, prev/next, «поделиться», комментарии.
- Загрузка (кабинет): drag&drop до 10/партия, 50/день, превью, подписи,
  прогресс; обработка — queued job.
- **ImageProcessor** (Intervention v4): валидация → EXIF-orientate →
  извлечение taken_at/камеры → downscale ≤2560 → medium (1280 WebP q80) +
  thumb (480 crop WebP q75) → пере-кодирование (анти-полиглот) →
  EXIF-strip → имена-хэши → `storage/app/public/photos/{Y}/{m}/`.
  Оригинал ≤8MB; GIF — первый кадр; SVG запрещён; исполняемые — блок.
- Альбомы: max 10; сортировка drag&drop.
- Автор фото для гостя — текст.

## 6.7 Дашборд сообщества

Приветствие («Привет, {ник}! Онлайн: N игроков, M гостей») →
сетка виджетов 3 колонки. Порядок — `sort_order`, ширина — `column_span`.
Гость — CTA «Войти».

**community_chat (span 2):**
- Последние 50 (SSR), подгрузка +50 при скролле вверх.
- `wire:poll.{chat_polling_interval}` (default 5с, допустимо 3–30; раз в
  секунду — запрещено) — догрузка `id > lastId`.
- Автоскролл внизу; уехал вверх — пилюля «↓ Новые сообщения (N)».
- Форма: textarea, Enter=отправка, Shift+Enter=перенос, 500 симв.; блок
  при `chat_banned_until`.
- Сообщение: `<x-user-identity context="chat">`, автолинковка, `H:i`,
  разделители «сегодня/вчера».
- **@упоминания:** `/@([a-zA-Z0-9_-]{3,24})/`; существующим (кроме
  автора; ≤5) — личное уведомление (queued); подсветка; упоминание
  закрытого — текст без ссылки.
- Антифлуд 5/30с. Модерация (`chat.moderate`): удалить (is_deleted +
  причина), мут (1ч/1д/7д/навсегда).

**events_board (span 1):** предстоящие 14 дней; карточка: бейдж типа,
дата+время, заголовок, лидер, место, «X/Y»/«∞», аватары (до 5 + «+N»),
«Записаться»/«Отменить». С учётом вместимости и `registration_close` —
«Регистрация закрыта». «+ Событие» → форма.

**activity_feed (span 2):** 15 последних с иконками (🎉📰📋✅💬🖼),
`<x-user-identity>`, снапшот, относительное время, «Вся активность →».
Смежные однотипные одного актора группируются. Ссылка на субъект —
только если существует и доступен. Битых ссылок нет.

**online_users (span 1):** счётчик + аватары (5 мин), клик → страница
игрока (если открыт).

**api_chart (span 1–3):** настройки в админке (title, URL, метод, тип,
JSONPath-маппинг, интервал, TTL). Серверный fetch (HTTP + file-кеш),
Chart.js в `wire:ignore` через Alpine. Timeout, allowlist, обработка
ошибок.

**html_board (span 1–3):** заголовок + Markdown/HTML (админ; HTML —
whitelist-санитизация: p, a, img, ul/ol/li, strong, em, h3-h4, table).

## 6.8 Колокольчик 🔔

Только auth; в header-right. Livewire, `wire:poll.30s`; бейдж (99+).

Dropdown, 2 вкладки: **Сообщество** (activities; непрочитанные =
`created_at > last_activity_seen_at`; «Отметить прочитанными») и
**Личные** (Laravel database notifications; клик = mark-as-read).
15 записей; каждая ведёт к объекту (в т.ч. якоря `#comment-{id}`).

**Личные:** комментарий на вашу новость/фото/событие; ответ на ваш
комментарий; запись/выход участника вашего события; отмена; одобрение/
отклонение комментария или player-новости (с причиной); @упоминание в
чате; напоминание за 1 час; смена пароля/email. Все рендеры через
`<x-user-identity>`.

## 6.9 Гости

- Middleware `IdentifyGuest`: нет cookie `guest_uid` → UUID (httpOnly,
  **30 дней**) + запись `display_name` = `guest001…`; есть → touch (≤1/мин).
- IP — только хэш.
- При регистрации → `converted_user_id`, `converted_at`.
- Видимость — только админка: «Гостей онлайн» + страница «Гости».
- Гостевой чат не реализуется (задел 5.8 + ADR).

## 6.10 ContentRenderer + HtmlSanitizer

Для `news.body`, `pages.body`, легенды, `html_board`, описаний событий.
Кэш в `*_html` при сохранении; парсинг на лету запрещён.

**Слой 1 — commonmark:** `html_input => 'strip'`,
`allow_unsafe_links => false`, Autolink. **Слой 2 — HtmlSanitizer**
(DOMDocument или htmlpurifier — ADR): whitelist `p, br, strong, em, del,
ul, ol, li, blockquote, code, pre, h2, h3, h4, hr, a`; опасные (script,
iframe, style, svg, form) — удаляются с содержимым; прочие — с
сохранением текста. У `a` — только `href` (http/https/mailto);
`on*`, `style`, `class`, `id`, `data-*` — срезаются; запрещённая схема
→ текст без ссылки. **Слой 3:** внешним — `rel="nofollow noopener
noreferrer" target="_blank"` + маркер ↗.

`<img>` в теле запрещён (только обложки/галерея). Никогда `{!! !!}`
без санитизации.

**Pest-датасет XSS:** `<script>`, `<img onerror>`, `[x](javascript:)`,
`data:text/html`, `vbscript:`, `jAvAsCrIpT:`, `"><svg onload`,
`<iframe>`, `<style>`, entity-обходы (`javascript&colon;`), Markdown в
link-title.

## 6.11 Закрытая регистрация

`registration_open=false`: GET — заглушка; POST — 403 (middleware).
Логин работает. Мгновенно (flush cache).

## 6.12 Удаление аккаунта

**Шаг 1** — кабинет → «Опасная зона»: мастер-модал (последствия; причина;
чекбокс «Понимаю»; **ввод текущего пароля**).

**Шаг 2** — немедленно (транзакция): `status='deletion_requested'`;
logout всех сессий; вход блокирован; контент скрыт (страница/новости/
фото → 404); события-лидерство → cancelled + уведомления; записи →
left + уведомление лидерам; комментарии/чат → «[аккаунт удалён]»;
письмо пользователю + уведомление админам + бейдж «Запросы на удаление».

**Шаг 3** — админ (Users → «Запросы на удаление»). Карточка: кто, когда,
причина, счётчики. Оба действия — с audit + **re-auth (пароль админа)**:

- **«Удалить полностью»:** soft delete + каскадный soft delete; затирка
  PII (email → `deleted-user-{id}@invalid.local`, phone → null, legend → null,
  password → random); аватары с диска; чат → is_deleted, reason=
  'account_deleted'; активности актора удалены; уведомления чистятся;
  **ник занят навсегда**.
- **«Восстановить»:** status → active, контент возвращается, письмо
  «Войдите и смените пароль».

FK-стратегия — явная.

## 6.13 Ранги игроков

- **Управление (admin, `ranks.manage`):** CRUD `user_ranks` в
  «Справочниках»; назначение в UserResource (Select). Удаление ранга →
  `rank_id = null`.
- **Отображение:** `<x-user-rank>` — компактно `{icon} {rating}` (title
  в tooltip/aria) рядом с ником; полный вид — в шапке страницы игрока.
  По матрице 3.4: авторизованным; **не показывается** гостям и в чате
  закрытых профилей.
- **Настройка:** `ranks_enabled` (default true).
- Ранг — публичная информация, на доступ не влияет.

## 6.14 Социальные ссылки

- **Кабинет:** список; add (платформа → username или URL), edit, delete,
  toggle is_visible, сортировка. Лимит — настройка (default 10).
- **Валидация (сервер):** только `https`; схемы-whitelist; блок
  `javascript:`/`data:`/`file:`; нормализация; ≤500; label/username
  экранируются. Для платформ с шаблоном — URL формирует система,
  валидируется.
- **Публично:** на странице игрока — только если профиль открыт и
  ссылка `is_visible`. Закрытый → блока нет. Гость → профилей нет.
- **Админ:** ссылки в карточке; удаление при нарушении (audit).

---

# 7. АДМИН-ПАНЕЛЬ (Filament)

Доступ: `canAccessPanel` = editor|admin. Навигация 4 группами.
Editor видит только «Контент».

## 7.1 Dashboard

**Admin:** статы (users всего/+неделя, news, comments на модерации
(бейдж), photos, гостей онлайн, events на неделе, delete-requests),
график 30 дней, 10 последних audit, шорткаты. **Все значения реальные.**

**Editor:** только контентные статы.

## 7.2 Сообщество (admin)

- **Users:** CRUD; роли; статусы (Активен / Забанен / Запрос / Удалён);
  назначение ранга; соцссылки (просмотр/удаление); повторная
  верификация; сброс пароля; soft delete; просмотр любого профиля;
  фильтры + вкладка «Запросы на удаление» с бейджем; действия 6.12
  (re-auth).
- **Guests:** список guest_visitors.

> Roles и Matrix относятся к группе навигации «Система» (см. §7.5),
> а не к «Сообществу».

## 7.3 Контент (admin + editor)

- **News:** таблица (заголовок, автор, scope, статус, дата, views,
  comments), фильтры (draft/pending/published/rejected/archived,
  Game/Player), поиск; форма (title, slug авто, excerpt, body Markdown,
  обложка 6.6, comments_enabled, scope, status, published_at, is_pinned);
  «Отклонить»/«Снять» с обязательной причиной; «В архив»; bulk; бейдж
  «На модерации: N». Editor — автор сам; смена автора — admin.
- **Comments:** очередь (pending/reported первыми), контекст, bulk
  approve/reject/spam, скрыть/удалить, edit, фильтры.
- **Gallery:** Albums CRUD (обложка, порядок) + Photos (пакетная
  загрузка, кэпшены, drag, bulk publish/unpublish/delete).
- **Pages:** CRUD + show_in_menu/menu_order.
- **Events:** CRUD, статус, перенос starts_at, участники, типы.

## 7.4 Виджеты (admin)

CRUD `dashboard_widgets`: title, type, is_active, sort_order
(reorderable), column_span, settings (динамическая форма; api_chart —
кнопка «Проверить API»).

## 7.5 Система (admin)

- **Roles:** CRUD + syncPermissions. Только admin, группа «Система».
- **Matrix:** таблица «раздел × роль» + guest-флаги; сохранение = sync
  `section.*.view` + guest-JSON. Только admin, группа «Система».
- **ActivityLog:** `admin_audit_logs` + community activities.
- **EventTypes:** CRUD.
- **Ranks:** CRUD.
- **Settings:** таблица ключей (`registration_open`, `player_news_
  moderation`, `comments_moderation`, `comments_rate_*`, `chat_enabled`,
  `chat_polling_interval`, `chat_max_length`, `chat_rate_*`,
  `events_enabled`, `gallery_max_albums_per_user`, `gallery_max_photo_mb`,
  `social_max_links_per_user`, `ranks_enabled`, `default_profile_public`,
  `bell_enabled`, `activity_enabled`, `notifications_enabled`,
  `activity_retention_days`, `admin_2fa_required`, `admin_ip_allowlist`,
  `admin_new_ip_notify`, `mfa_global_enabled`, `timezone_display`,
  `guest_chat_enabled` (reserved)). Хранение — `settings` (key/value json),
  кеш flush при сохранении.

> **MFA (ADR-009).** Security-секция `SettingsPage` — основное место глобальных
> MFA-настроек и обязательный escape-hatch для admin: доступна независимо от
> MFA-verification. `mfa_global_enabled` (default true) — глобальный gate;
> `admin_2fa_required` действует только при `mfa_global_enabled=true` и не влияет
> на editor/user. Отдельная страница `/admin/settings/security` **не создаётся**.
> Изменения MFA-настроек — только через `SettingsRepository::setMany()` с
> инвалидацией `settings.all`.

## 7.6 Усиленный вход admin

- **MFA (TOTP), ADR-009 — unified site-wide.** Единый challenge `/mfa/challenge`
  для user/editor/admin. `mfa_global_enabled` (default true) — глобальный gate;
  `admin_2fa_required` действует только при `mfa_global_enabled=true`:
  `false` → admin MFA opt-in, `true` → admin обязан. editor/user — всегда opt-in;
  `admin_2fa_required` на них не влияет. При `mfa_global_enabled=false`
  enforcement выключен, настройки MFA сохраняются.
- Verification действует **до logout** (session инвалидируется при logout);
  trusted devices и длительные TTL не используются.
- Escape-hatches (доступны без пройденного MFA): `/cabinet/security`
  (setup/disable/recovery) и `/admin/settings` (глобальные настройки, включая MFA).
- Enforcement-матрица по маршрутам — ADR-009 §2.3 (user/editor — `/players`;
  editor — контентные `/admin/*`; admin — `/admin/*` кроме `/admin/settings`).
- **Административный MFA reset** — permission `mfa.manage` (admin-only, см. §3.3);
  editor недоступен; reset-all не реализуется.
- Rate limit 5/мин/IP + email о входе с нового IP.
- Опциональный IP-allowlist.
- Чувствительные операции (удаление, смена ролей/прав, security, disable MFA) —
  re-auth + audit.
- Все мутации → `admin_audit_logs`. Сессии — database.

---

# 8. FRONT-END

## 8.1 Интеграция макетов

- `wireframe.html` → `layouts/app.blade.php` как есть: фиксированный
  header/footer, CSS-переменные, тёмная тема (localStorage →
  prefers-color-scheme, без ломки SSR). Новые состояния — только через
  существующие токены.
- Header: auth — аватар-меню + 🔔; гость — как в макете. На логине —
  ссылка «Создать аккаунт». Мобильная адаптация — по media-запросам.
- `admin.html` — UX-ориентир, реализовано средствами Filament 5.

## 8.2 Blade-компоненты

`x-user-identity` (центральный), `x-user-rank`, `x-social-links`,
`x-news-card`, `x-player-card`, `x-photo-grid`, `x-comment-thread`,
`x-event-card`, `x-activity-item`, `x-avatar` (fallback: инициал +
цвет из хэша).

## 8.3 Livewire

`NewsFeed`, `CommentThread`, `CommentForm`, `ChatRoom`, `EventsBoard`,
`EventSignup`, `ActivityBell`, `GalleryGrid`, `OnlineUsers`,
`PlayerDirectory`, `RegistrationForm`,
`Cabinet\{ProfileForm, SocialLinks, AvatarUploader, NewsEditor,
AlbumManager, EventForm, DangerZone}`, `Widgets\ApiChart`.

## 8.4 UX-состояния

- **Empty states** с CTA в каждом списке.
- **Страницы ошибок** в стиле сайта: 403, 404, 419 (сессия — кнопка
  перезагрузки), 422, 429 (таймер), 500.
- **Формы:** ошибки инлайн; ввод не теряется; loading + блок повторной
  отправки; success-фидбек (toast).
- **A11y:** aria-label на иконки; focus-visible; alt из кэпшенов;
  клавиатура в лайтбоксе; semantic headings; кнопки вместо clickable-div;
  контраст из токенов.
- **Производительность:** пагинация, eager loading (rank, avatar),
  индексы 5.x, lazy images, thumbnails.

---

# 9. ТЕСТИРОВАНИЕ (Pest) — ПРИЁМОЧНЫЕ СЦЕНАРИИ

1. **Регистрация:** занятый ник (`Jumxi` vs `jumxi`) → 3 подсказки
   свободны; верификация разблокирует write.
2. **Дубликат email** → ошибка валидации; дубликат ника → «занят» +
   подсказки.
3. **До верификации:** чтение ок; write → 403.
4. **Player-новость** → видна на странице игрока и в 🔔.
5. **Приватность:** закрытый профиль — ник+аватар без ссылки (чат —
   только ник); URL его контента → 404; открытый — всё.
6. **Гость:** не открывает `/players*`, `/activity`, чат; на `/news`
   видит комментарии и текстовые ники; события — организатор текстом,
   участники количеством; формы → CTA.
7. **Комментарии (reputation):** первые 5 pending, 6-й approved; bulk;
   spam скрыт.
8. **`comments_enabled=false`:** секции нет, POST отклонён.
9. **Архив:** новость вне ленты/sitemap; URL — с плашкой.
10. **Чат:** инкрементальная выборка; rate limit; мут; удаление.
11. **Упоминания:** уведомление (не себе; ≤5; неизвестный игнор);
    подсветка; закрытый — без ссылки.
12. **События:** лидер auto; full → кнопка недоступна;
    `registration_close` → «Закрыта»; выход освобождает; напоминание;
    фильтр по типу.
13. **Галерея:** 10 файлов → 3 размера + WebP + EXIF stripped +
    полиглот убит; лимиты.
14. **Роли:** editor — только «Контент»; user → `/admin` 403.
15. **Гости-трекинг:** один visitor на cookie; `guest001`; конверсия.
16. **🔔:** счётчик по `last_activity_seen_at`; вкладки; отметить всё;
    личное = прочитано.
17. **Матрица:** снятие «User → Игроки» → 403; снятие guest-флага →
    404; мгновенно.
18. **Удаление:** без пароля — ошибка; с паролем — статус, сессии
    убиты, вход блокирован; контент скрыт; лидерские события отменены;
    «Удалить полностью» с re-auth → PII затёрты, каскад, ник занят;
    «Восстановить» — работает.
19. **Модерация:** pre (pending только автору, 404 другим, одобрение/
    отклонение с причиной) и post (мгновенно, снятие с причиной);
    переключение не ретроактивно.
20. **Санитизация:** полный XSS-датасет; rel/target внешним; img в
    теле запрещён.
21. **Соцсети:** `http://`, `javascript:`, `data:` отклонены;
    `is_visible=false` скрыт; закрытый — блока нет; лимит; шаблон
    username→URL валиден.
22. **Ранги:** только админом; бейдж виден auth (кроме чата закрытых);
    гостям не виден; `ranks_enabled=false` — нигде; удаление снимает
    бейджи.
23. **Закрытая регистрация:** GET-заглушка, POST-403, логин работает.
24. **Разделение пространств:** `/cabinet` — только владелец;
    «Предпросмотр» корректен.
25. **Enumeration:** reset/resend — единый ответ.
26. **Activity:** `comment_created` создаётся; группировка; ссылка на
    недоступное не рендерится.
27. **MFA (ADR-009):** единый acceptance matrix T1–T24 —
    `docs/ai/E10-MFA-CONTRACT.md` §4 (global gate, роли, escape-hatches,
    challenge, recovery, logout, admin reset, no double challenge).

**Unit:** NicknameSuggester (включая CI); генерация excerpt; маппинг
api_chart; группировка activity; URL-нормализация соцссылок.

---

# 10. ВНЕ ОБЪЁМА MVP

Личные сообщения, друзья/подписки, лайки, гостевой чат (задел готов),
REST API, мультиязычность, emoji-реакции, вложения в чат,
автоприсвоение рангов, attended/no_show, waitlist, приватные события,
часовой пояс события, preferences уведомлений, упоминания в
комментариях, серверная синхронизация темы.

---

# ИСТОРИЯ ВЕРСИЙ

**1.2** — E10.1 reconciliation (D1): permission `mfa.manage` добавлен в §3.3
(admin-only, только для административного MFA reset; self-service им не
защищается; reset-all не реализуется). Ссылки на enforcement-матрицу ADR-009 §2.3
и admin reset добавлены в §3.1 и §7.6. Продуктовые решения не менялись.

**1.1** — reconciliation с `ADR-009` (unified site-wide MFA): §3.1 (MFA-политика
ролей), §4 (`/mfa/challenge`), §6.2 (MFA в Security-вкладке кабинета),
§7.5 (`mfa_global_enabled`), §7.6 (усиленный вход admin переписан на единый
challenge, escape-hatches, verification до logout), §9 (scenario 27 → matrix
T1–T24). Продуктовые решения не менялись — только согласование с новой
MFA-моделью.

**1.0** — первая версия. Интеграция с `context.md` v4.0 (без дублей:
стек, инфраструктура, деплой — в context.md; функционал — здесь).
Зафиксированы продуктовые решения §1, единая матрица приватности §3.4,
маршруты §4, 13 моделей §5, функциональная спецификация §6, админка
§7, frontend §8, 26 приёмочных сценариев §9.
