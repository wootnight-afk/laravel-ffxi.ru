# ADR-007: Источник истины для матрицы доступа к публичным секциям

| Поле | Значение |
|---|---|
| Статус | Принят |
| Дата | 2026-10-02 |
| Этап | 6 — Профили и кабинет (context.md §30) |
| Связанные разделы | context.md §13, §37; frontend-spec.md §3.2, §3.3; adminreview.md §4 |
| Причина выделения | Конфликт между context.md §13 и adminreview.md §4.1 |

---

## 1. ПРОБЛЕМА

Матрица доступа к публичным секциям (кто видит `/news`, `/gallery`, `/players`, `/cabinet` и т. д.)
описана в трёх документах:

| Документ | Раздел | Модель хранения |
|---|---|---|
| `docs/ai/context.md` | §13 | spatie `section.{key}.view` для ролей + JSON `guest_sections` в `settings` для гостей |
| `docs/ai/frontend-spec.md` | §3.2 | Та же модель; §3.3 приводит список permission-кодов |
| `docs/adminreview.md` | §4.1 | Таблица `page_role_access (page_slug, role_slug)` |

Две модели несовместимы:

- `page_role_access` — своя таблица, дублирует роли (`role_slug` строкой, не FK) и создаёт второй источник истины рядом со spatie.
- `section.*.view` + `guest_sections` — использует уже принятый `spatie/laravel-permission` и `settings` (см. context.md §13, §3).

Пока не зафиксирована одна модель, этап 8 (Filament — `PermissionsMatrixPage`, `RoleResource`) может быть построен поверх `page_role_access`, что потребует последующей переделки и приведёт к расхождению с проверками middleware.

---

## 2. РЕШЕНИЕ

**Источник истины — модель `context.md` §13 и `frontend-spec.md` §3.2.**

Принятая модель:

1. **Для ролей** (`admin`, `editor`, `user`) — spatie-разрешения вида
   `section.{home|news|gallery|contacts|events|players|player_profiles}.view`,
   создаваемые сидером `RoleAndPermissionSeeder`. Роли имеют разрешения через
   `role_has_permissions`.
2. **Для гостей** — JSON-массив `guest_sections` в таблице `settings`
   (`{ home: true, news: true, ..., players: false, player_profiles: false }`),
   редактируемый через Settings (этап 8). Значения по умолчанию задаются
   `SettingsSeeder`.
3. **Middleware** `section.access:{key}` (`App\Http\Middleware\SectionAccess`):
   для авторизованного пользователя — проверка permission; для гостя —
   проверка `guest_sections[key]`. При отсутствии доступа — 404/редирект.
4. **`Gate::before` для admin** — возвращает `true` только для abilities
   с префиксом `section.`. Для остальных abilities callback не даёт обхода
   (`null`); применяются обычные Spatie permissions и Policies. Явные
   запреты Policies, включая запрет self-delete и self-demotion, сохраняются.

**Таблица `page_role_access` не вводится.** Ни миграция, ни модель, ни
Filament-ресурс для неё не создаются. В `adminreview.md` §4.1 остаётся
исторический черновик (до этой фиксации) и при следующей редакции документа
подлежит удалению с ссылкой на ADR-007.

Изменения прав через UI (этап 8) реализуются через `syncPermissions` для ролей
и через `SettingsRepository::set('guest_sections', ...)` для гостевых флагов.

---

## 3. ПРИЧИНЫ

### 3.1. Правило разрешения конфликтов из context.md §37

> Стек, безопасность, деплой, backup, обновления → context.md
> Функционал (модели, маршруты, роли, бизнес-логика, приёмки) → frontend-spec.md
> Вёрстка, страницы, компоненты, UX → designreview.md + wireframes
> Админ-ресурсы, матрица, аудит → adminreview.md

Раздел `adminreview.md` §4 описывает админ-ресурс и матрицу. Но **сама
матрица** — это функциональная часть (кто что видит на публичном сайте),
а не UI-деталь админки. На конфликт между «как показывать» (adminreview.md §4.3)
и «что хранить» (context.md §13) действует **приоритет функциональной
спецификации и технического контекста**.

Дополнительно: context.md §37 явно перечисляет «безопасность» и «модель
данных» как зону приоритета context.md. Матрица доступа — обе эти зоны.

### 3.2. Единый источник ролей

`adminreview.md` сам в §3.2 фиксирует:
> spatie/laravel-permission ^8.3 — единственный источник истины по ролям
> и разрешениям.

Введение `page_role_access` с полем `role_slug varchar(32)` создало бы
**второй** список ролей рядом со spatie (`roles.name`), синхронизируемый
вручную. Это классическая проблема двойного источника истины — при
переименовании роли придётся править обе таблицы.

### 3.3. Мгновенная инвалидация кеша

Модель `guest_sections` в `settings` уже имеет механизм инвалидации
(`SettingsRepository::flush()` очищает `settings.all`, context.md §13).
Таблица `page_role_access` потребовала бы собственного кеша с
собственной инвалидацией и собственным Cache-ключом.

### 3.4. Модель уже реализована и покрыта тестами

На момент создания ADR в кодовой базе **уже существуют**:

- `app/Http/Middleware/SectionAccess.php` — читает spatie-permission для
  роли и `guest_sections` для гостя (проверяется косвенно через тесты
  публичных маршрутов).
- `database/seeders/RoleAndPermissionSeeder.php` — создаёт разрешения
  `section.*.view` и связывает их с ролями.
- `database/seeders/SettingsSeeder.php` — создаёт ключ `guest_sections`
  (подтверждено `SELECT` из `settings` в ранних сессиях).
- `resources/views/cabinet/show.blade.php` — использует `TABS`, не таблицу
  `page_role_access`.

Смена модели на `page_role_access` сейчас означала бы переписывание этих
файлов и их тестов — необоснованное «улучшение».

### 3.5. Дизайн-документ допускает отставание

`adminreview.md` — ревью админки, а не технический контракт. По правилу
context.md §37 «при отсутствии ответа в design-документах — вопрос
пользователю до генерации файлов». Здесь ответ есть в spec-документах,
значит вопрос закрывается без участия пользователя.

---

## 4. АЛЬТЕРНАТИВЫ

| Вариант | Решение |
|---|---|
| Таблица `page_role_access` (adminreview.md §4.1) | Отклонён: второй источник истины по ролям, дублирует spatie, требует собственной инвалидации кеша. |
| Гибрид: `page_role_access` + spatie | Отклонён: максимальная сложность, два места правды, синхронизация вручную. |
| Только spatie, без guest_sections | Отклонён: гости не имеют `user_id`, spatie-roles к ним неприменимы. JSON в `settings` — простой и достаточный механизм. |
| Отдельные permission-ы `guest.{key}.view` через spatie | Отклонён: гости не аутентифицированы, spatie рассчитан на `User`-модели. |
| **spatie `section.*.view` + `guest_sections` JSON (текущая модель)** | **Принят.** |

---

## 5. ПОСЛЕДСТВИЯ

### 5.1. Положительные

- **Один источник истины по ролям** — spatie.
- **Один источник истины по матрице** — context.md §13.
- Изменения прав через этап 8 (Filament): `syncPermissions` + `SettingsRepository::set`.
- Кеш инвалидируется автоматически при сохранении настроек.
- Middleware `SectionAccess` — уже реализован и используется; менять не нужно.
- `adminreview.md` §4.1 → исторический черновик, при следующей редакции
  заменяется ссылкой на ADR-007.

### 5.2. Отрицательные и принятые риски

1. **Дизайн-документ `adminreview.md` §4.1 остаётся в репозитории и формально
   противоречит ADR.** Митигация: этот ADR, ссылки в комментариях при
   реализации `PermissionsMatrixPage` (этап 8). Правка самого
   `adminreview.md` — отдельная задача (не входит в A.13).
2. **`guest_sections` — JSON, а не типизированные поля.** Опечатка в ключе
   приведёт к «не находим значение → секция закрыта». Митигация:
   фиксированный список ключей в `SettingsSeeder`, тесты публичных
   маршрутов от имени гостя.
3. **Middleware `SectionAccess` не покрывает wildcard-секции** (например,
   `/cabinet/*`). Оно и не должно: кабинет регулируется `auth` +
   ownership, а не матрицей. Явное ограничение области применения.

### 5.3. Условия пересмотра

- Появление требования «матрица доступа к страницам CMS, созданным через
  `PageResource`», не сводящегося к фиксированному списку секций.
  (В этом случае — новый ADR, а не правка этого.)
- Обнаружение несоответствия между middleware и тестовым сценарием
  `frontend-spec.md §9.17` (матрица: снятие флага → 403, снятие гостевого →
  404).

---

## 6. ССЫЛКИ НА РЕАЛИЗАЦИЮ

Код, соответствующий решению ADR-007 (не требует изменений):

| Файл | Роль |
|---|---|
| `app/Http/Middleware/SectionAccess.php` | Читает spatie-permission или `guest_sections` |
| `database/seeders/RoleAndPermissionSeeder.php` | Создаёт `section.*.view` и привязывает к ролям |
| `database/seeders/SettingsSeeder.php` | Создаёт `guest_sections` |
| `app/Services/SettingsRepository.php` | Публичный API к `settings` + flush кеша |
| `docs/ai/context.md` §13 | Источник истины (этот ADR фиксирует его) |
| `docs/ai/frontend-spec.md` §3.2, §3.3 | Прикладная модель и список permission-кодов |

Код, **не создаваемый** в рамках этого решения:

- Миграция `create_page_role_access_table` — не будет.
- Модель `App\Models\PageRoleAccess` — не будет.
- Filament `PermissionsMatrixPage` будет работать с `Role + Permission`
  и `SettingsRepository`, а не с `page_role_access` (этап 8).
