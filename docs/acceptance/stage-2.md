# Приёмка Stage 2 — активный персонаж и контекст Players

- **Статус:** PASSED — проверки выполнены 2026-11-18, ожидает подтверждения
  владельцем. Коммит и push не выполнялись.
- **ТЗ:** `docs/ai/STAGE-2-ACTIVE-CHARACTER.md` v1.2 (согласовано 2026-11-18,
  схема FK — вариант A).
- **Базовая точка Git:** `e47a704`.
- **База тестов:** 1257 passed (4574 assertions) — финальный прогон Stage 1
  (`STAGE-1-CABINET-CHARACTERS.md` §21.5). Фактический прогон Stage 2:
  **1287 passed (4671 assertions), 244.89 s, 0 failed** (+30 тестов, +97 assertions).

## 1. Объём

Хранение активного персонажа (`user_active_characters`), `CharacterContext`,
middleware разрешения контекста, переключение в Players и кабинете, фильтр
сервера и его влияние на `players.directory`, состояния и коды ответов,
локализация RU/EN, тесты, геометрия меню, документация.

Вне объёма: атрибуция действий (этап 3), каталог и страницы персонажей
(этап 4), галерея, глобальный футер, `KODA.md`, `.kodarules`.

## 2. Обязательные проверки

| # | Проверка | Фактический результат | Статус |
|---|---|---|---|
| 1 | Миграции на MySQL 8.4: `migrate --pretend` | SQL содержит `UNIQUE (id, user_id)` на `game_characters`, составной FK; обычного FK `character_id` и триггеров нет | PASS |
| 2 | Реальное применение миграций | Тестовая БД: `migrate:status` — обе миграции `Ran`. Dev-БД: применены точечно (`--path`), 42.72 ms и 218.85 ms, DONE | PASS |
| 3 | `SHOW CREATE TABLE` | `PRIMARY KEY (user_id)`, `UNIQUE (character_id)`, `KEY (character_id, user_id)`, составной FK `ON DELETE CASCADE`, FK `user_id → users`, обычного FK нет; `game_characters`: `UNIQUE game_characters_id_user_id_unique (id, user_id)`; `restrict_fk_on_non_standard_key = 1` | PASS |
| 4 | Полный Pest | **1287 passed (4671 assertions)**, 244.89 s, провалов 0; прирост +30/+97 к базе 1257 | PASS |
| 5 | Pint `--test` | PASS, 532 файла | PASS |
| 6 | Larastan | `[OK] No errors` | PASS |
| 7 | Атомарность удаления | `ActiveCharacterDeletionTest`: исключение внутри транзакции откатывает и `deleted_at`, и указатель | PASS |
| 8 | Независимость: персонаж → фильтр | `ServerFilterTest`: смена персонажа не меняет `players.server_filter` | PASS |
| 9 | Независимость: фильтр → персонаж | `ServerFilterTest`: смена фильтра не меняет `user_active_characters` | PASS |
| 10 | Владение | Чужой / удалённый / несуществующий id → 404, значение не меняется | PASS |
| 11 | Валидация | 422 (JSON) / 302 + ошибки (форма) | PASS |
| 12 | Состояние аккаунта | Suspended / DeletionRequested / banned → контекст `null`, переключение 403 | PASS |
| 13 | GET не пишет | «Осиротевший» указатель не изменяется при открытии страниц | PASS |
| 14 | Сохранение между сессиями | Значение переживает выход и повторный вход | PASS |
| 15 | Геометрия 1440×900 | navTop 56 = headerBottom 56; navBottom 812, footerTop 813; прокрутка панели 0, обрезанных пунктов 0, hScroll 0; блок 127 px видим | PASS |
| 16 | Геометрия 1280×720 | Прокрутка 0, обрезанных пунктов 0, hScroll 0; блок видим (127 px). Панель доходит до 680 при footerTop 633 — поведение fixed-панели, не вызвано Stage 2 | PASS |
| 17 | Геометрия 1024×594 | Прокрутка 0, обрезанных пунктов 0, hScroll 0; блок видим; ограничение 576–594 px не расширено | PASS |
| 18 | Геометрия 390×844 | Блок видим (108 px, `ctxVisible: true`), hScroll 0, обрезанных пунктов 0, горизонтальная навигация сохранена | PASS |
| 19 | RU/EN | RU: «Активный персонаж», «Переключить», «Игровой сервер», «Все серверы», «Применить». EN: «Active character», «Switch», «Game server», «All servers», «Apply». Сырых ключей нет | PASS |
| 20 | Галерея | В диффе нет галерейных контроллеров, моделей, маршрутов, шаблонов и миграций | PASS |
| 21 | Глобальный футер | `footer.text_ru/en` и шаблон футера не изменены | PASS |
| 22 | Реестр страниц | Покрыт тестами `SitePageRegistry` в полном прогоне; новых POST/DELETE-маршрутов в реестре нет (классифицируются как `write`) | PASS |
| 23 | `git diff --check` | Без ошибок форматирования | PASS |

## 3. Границы, подтверждаемые по диффу

- `app/Http/Controllers/PlayerGalleryController.php`, `GalleryController.php`,
  `PhotoController.php`, `Cabinet/MyGalleryController.php`;
- модели `Album`, `Photo`; `ProcessPhotoJob`;
- галерейные маршруты, шаблоны и миграции;
- `app/Http/Middleware/SectionAccess.php`, `RequireMfa.php`;
- `resources/views/players/_sidebar.blade.php`, `players/show.blade.php`;
- `KODA.md`, `.kodarules`.

## 4. Итог

- Дата проверки: 2026-11-18
- Итог внутренней проверки: **PASSED** (23 из 23 пунктов), регрессий не выявлено
- Решение владельца: ожидается
- Наблюдения вне Stage 2: неполный EN-перевод части существующих строк
  («Активность», «Помощь новичкам», «Другое», «Вся активность») — существовало
  до этапа, ключи Stage 2 переведены полностью.
- Открытые замечания: нет
