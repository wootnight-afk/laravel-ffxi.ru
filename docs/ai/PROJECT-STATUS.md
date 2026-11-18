# Project Status — laravel-ffxi.ru

## Stage 2 «Активный персонаж и контекст Players» — реализован (2026-11-18)

ТЗ согласовано и реализовано в границах этапа; код, миграции и документация
обновлены. Коммиты и push выполняет отдельный Git-агент.

- **Базовая точка Git:** `e47a704` (`origin/main`), ahead/behind `0/0`.
  Stage 0/1 зафиксированы и отправлены 2026-11-18. Коммиты и push этапа 2
  выполняет отдельный Git-агент.
- **Основной документ этапа:** `docs/ai/STAGE-2-ACTIVE-CHARACTER.md` (v1.2) —
  согласованные решения Q1–Q10 (Q7 — вариант A), схема данных, серверная часть,
  интерфейс, инвариант независимости, атомарность удаления, тесты, критерии
  приёмки, карта файлов, порядок работ, фактические результаты. Отчёт приёмки —
  `docs/acceptance/stage-2.md`.
- **Согласованные решения:**
  - Q1 — фильтр сервера сужает существующую выдачу `/players/directory`
    до аккаунтов с публичным персонажем выбранного сервера; каталог публичных
    персонажей этапа 4 не создаётся;
  - Q2 — переключатель доступен на мобильных через независимый блок
    `.players-context` (не наследует скрытие `__footer` на `≤1023px`);
  - Q3 — нативный `select` + кнопка «Переключить», без JS-состояния;
  - Q4 — переключение также в кабинете персонажей (маркер + кнопка);
  - Q5 — 422 (нет поля / не число), 404 (чужой, удалённый, несуществующий —
    неразличимы), 403 (состояние аккаунта);
  - Q6 — контекст `null` и отказ в переключении только при `status !== Active`,
    `isBanned()`, `isDeletionRequested()`, `isSuspended()`; новых middleware
    и глобальных блокировок не вводится;
  - Q7 — **вариант A.** Опора составного FK — `UNIQUE (id, user_id)` на
    `game_characters`: MySQL 8.4 с `restrict_fk_on_non_standard_key = ON`
    (проверено в проектном окружении: 8.4.11, `ON`) не допускает FK на
    неуникальный ключ. Связь с персонажем — единственный составной FK
    `(character_id, user_id) → game_characters(id, user_id)` с
    `ON DELETE CASCADE`; обычный FK `character_id → game_characters(id)`
    не создаётся, триггеры не добавляются, настройки MySQL не меняются;
    владение персонажем обеспечивает схема БД;
  - Q8 — фильтр изменяется `POST` (PRG);
  - Q10 — заменяется только блок внутри меню Players; глобальный футер
    и `footer.text_ru/en` не затрагиваются.
- **Обязательные критерии реализации:** схема по варианту A подтверждена
  на MySQL 8.4 (`migrate --pretend`, up/down, `SHOW CREATE TABLE`) —
  `UNIQUE (id, user_id)` на `game_characters`, единственный составной FK,
  отсутствие обычного FK и триггеров, `restrict_fk_on_non_standard_key`
  остаётся `ON`; фактический DDL прилагается к отчёту; атомарность soft delete
  активного персонажа и снятия указателя в одной транзакции
  `CharacterTransaction` (`GameCharacterSaver::delete()`), обработчик
  `deleting` — идемпотентная страховка; независимость активного персонажа
  и фильтра сервера проверяется тестами в обоих направлениях; база Pest —
  **1257 passed (4574 assertions)** (финальный прогон Stage 1, §21.5).
- **Результаты проверок (2026-11-18):** полный Pest — **1287 passed
  (4671 assertions)**, 244.89 s, провалов 0 (+30 тестов, +97 assertions — ровно
  новые тесты этапа); Pint `--test` — PASS (532 файла); Larastan — без ошибок;
  `npm run build` — успешно. Схема применена реально на MySQL 8.4 в dev-
  и тестовой БД, DDL снят: `UNIQUE (id, user_id)`, единственный составной FK,
  обычного FK и триггеров нет, `restrict_fk_on_non_standard_key = 1`.
  Визуальная проверка 1440×900 / 1280×720 / 1024×594 / 390×844: внутренней
  прокрутки, обрезки пунктов и горизонтальной прокрутки нет; блок виден
  на мобильном. Кабинет: маркер и кнопка на существующих страницах, второго
  раздела и дублирующих форм нет. RU/EN — сырых ключей нет.
- **Обновлённые документы:** `CHARACTERS-PLATFORM.md` (§4.1, §4.2, §4.4.3,
  §4.4.5, §6.1–§6.3, §11 этап 2, §12, §13 R1/R17–R20, §15),
  `ADR-013` (Accepted частично: §2.2, §2.3, §2.9 в объёме этапа 2;
  §2.4–§2.6 остаются Proposed), `designreview.md` §11,
  `frontend-spec.md` §8.1, `docs/acceptance/stage-2.md` (план приёмки).
- **Статус:** Stage 2 — **реализован**. Схема: `UNIQUE (id, user_id)` на
  `game_characters` + таблица `user_active_characters` с единственным составным
  FK `(character_id, user_id) → game_characters(id, user_id)` и каскадом;
  обычного FK на `character_id`, триггеров и изменений настроек MySQL нет.
  Владение персонажем обеспечивает схема БД. Реализованы `CharacterContext`,
  `ResolveCharacterContext`, переключение (Players и кабинет), независимый
  POST-фильтр сервера для `/players/directory`, блок `.players-context` в меню
  Players и атомарное снятие указателя при удалении персонажа.

## Stage 1 «Кабинет и управление персонажами» — принят и закрыт (2026-11-17)

Реализация выполнена по `docs/ai/STAGE-1-CABINET-CHARACTERS.md`; документ
обновлён до v1.14 с разделами §14 «Стабилизация», §15 «Аудит и визуальная
проверка», §16 «Двуязычная легенда», §17 «Двуязычное описание профиля»,
§18 «Единое правило локализованных текстов», §19 «Header и локализация
пунктов Footer» и §20 «Footer Editor — единственный источник подписей».

- Управление персонажами перенесено в кабинет: `/cabinet/characters`,
  `/cabinet/characters/{character}`; старый `/players/characters` — временный
  alias (302) в прежней группе middleware; дублирующего места управления нет.
- Данные персонажа: `race`, `main_job`, `legend`, `legend_html` и таблица
  `game_character_jobs` (уникальность `game_character_id` + `job`).
- Двуязычная легенда: независимые пары `legend_ru`/`legend_ru_html` и
  `legend_en`/`legend_en_html`; страница показывает только версию текущей
  локали, форма редактирует обе, подстановки другого языка нет. Прежние
  `legend`/`legend_html` сохранены (expand-only миграция). Существующие легенды
  распределены командой `characters:legend-locale`
  (`--report`/`--dry-run`/`--apply`) детерминированным детектором алфавита;
  неоднозначные тексты не изменяются и попадают в отчёт. Результат на текущих
  данных: 2 персонажа, 1 легенда → EN, в RU — 0, неоднозначных — 0.
- Двуязычное описание профиля: `users.legend_ru`/`legend_ru_html` и
  `users.legend_en`/`legend_en_html` (expand-only миграция; прежние `legend` и
  `legend_html` не изменены). Блок «Описание профиля» на `/cabinet/profile`
  показывает только версию текущей локали с Markdown и санитизацией, форма
  редактирует обе версии независимо и не сбрасывает вторую. Перенос
  существующего текста — `characters:legend-locale --profiles` тем же
  детектором: 5 аккаунтов, 1 описание → RU, EN — 0, неоднозначных — 0.
  Детали — §17 документа этапа.
- Единое правило локализованных текстов (§18): на формах редактирования
  легенды персонажа и описания профиля показывается только поле текущей
  локали; вторая версия хранится независимо и не перезаписывается частичным
  сохранением. Публичный профиль игрока (`players/show`) выводит версию
  текущей локали вместо legacy `legend_html`. Выбор аватара на
  `/cabinet/profile` локализован («Выберите файл» / «Choose file», «Файл не
  выбран» / «No file chosen») при сохранении стандартной multipart-отправки и
  серверной валидации. CMS-страницы «Правила сообщества»
  (`pravila-soobschestva`) и «О сообществе» (`o-soobschestve`) получили
  полный английский контент (`title_en`, `body_en`, `body_html_en`,
  `meta_*_en`) в `DemoContentSeeder` и в рабочей БД; русские тексты не
  изменены. Перспективно (не реализовано): английский как язык интерфейса по
  умолчанию, `default locale` не менялся.
- Header и локализация футера (§19): резерв под фиксированную шапку больше не
  жёсткий — `initHeaderOffset()` измеряет реальную высоту `body > header` и
  пишет её в `--header-height`, поэтому под меню нет белой полосы на desktop
  (было 76 px резерва при 56 px шапки) и контент не уезжает под шапку на
  mobile (шапка 173 px при резерве 120 px). Детали — §19 документа этапа.
- Подписи пунктов меню (§20): единственный источник — Footer Editor
  (`label_ru`/`label_en` в `settings`, ключи `header.items`/`footer.items`).
  Публичный футер показывает подпись текущей локали, при пустой английской —
  русскую (per-field fallback, ADR-011 §2.20). CMS-страница отвечает только за
  контент и цель ссылки (связь по ID): URL, порядок и изображение от локали не
  зависят. Сохранение страницы больше не перезаписывает заполненные подписи —
  только инициализирует пустые. Использование `title_en` страницы из §19 как
  источника подписи устранено. Детали — §20 документа этапа.
- Двуязычная надпись футера (§21): «Community of Players Final Fantasy XI» —
  не пункт меню, а отдельная настройка Footer Editor `footer.text_ru` /
  `footer.text_en` в `settings` (без новой таблицы и без второго механизма
  хранения). Публичный футер выводит `footer.text_ru` при `ru` и
  `footer.text_en` при `en`; пустое EN → русское значение (per-field fallback,
  ADR-011 §2.20). Сохранение сбрасывает кэш `settings.all`, поэтому изменение
  видно сразу. В рабочей БД RU — «Содружество игроков Final Fantasy XI», EN —
  `Community of Players Final Fantasy XI`. Детали — §21 документа этапа.
- Единый механизм записи: `CharacterTransaction` (владение транзакцией по
  уровню, блокировки, savepoint, повторы только у владельца),
  `GameCharacterSaver`, `LegacyGameDataImporter`, `LegacyGameDataRollback`.
- Legacy-перенос: команда `characters:legacy-game-data` с `--report`,
  `--dry-run`, `--apply` (`--overwrite`) и `--rollback`; журнал
  `legacy_game_data_transfers` с нормализованным и контрольным хешами.
- Стабилизация: причина падения `/cabinet/characters` — три миграции этапа
  оставались `Pending` на рабочей БД `laravel_ffxi`; применены только они
  (`--path`), схема сверена с моделями и связями.
- Дефекты сохранности данных при частичном обновлении исправлены: отсутствующий
  ключ больше не сбрасывает `jobs`, `race`, `main_job`, `legend`, `is_public`;
  снятие публичности работает через скрытое поле формы.
- Аудит: в тесте идемпотентности `batch_id` выбирался запросом без `ORDER BY`
  (произвольный batch) — тест усилен и детерминирован. Найден UX-дефект: блок
  ручного переноса оставался после успешного переноса; `pendingFor()` скрывает
  его, когда все legacy-поля уже привязаны (добавлены регрессионные тесты).
- Проверки: Larastan — 0 ошибок, Pint — PASS (429 файлов, 1 исправление
  стиля), полный Pest на MySQL — `1257 passed (4574 assertions)`, exit 0
  (финальный прогон, §21.5; промежуточный прогон §18.5, до подэтапа
  локализации футера, дал `1242 passed (4498 assertions)`);
  ручная проверка UI 1440×900 / 1280×720 / 390×844 на RU и EN без
  горизонтальной прокрутки. Детали и цифры — §15, §18.5 и §21.5 документа этапа.
- Статус: Stage 1 **ACCEPTED — принят и закрыт 17.11.2026**. Все согласованные
  функции этапа реализованы и проверены, открытых пунктов Stage 1 нет.
  Следующий шаг — отдельное согласование плана Stage 2 по
  `docs/ai/CHARACTERS-PLATFORM.md` §9 (активный персонаж и единый контекст
  деятельности); реализация Stage 2 не начата. Массовые `--apply` и
  `--rollback` legacy-переноса на реальных данных не запускались.

## Аккаунт, персонажи и единый контекст деятельности — Stage 0 выполнен (документация, 2026-11-17)

Подготовлена архитектурная документация перед реализацией. Код приложения,
схема БД, маршруты, тесты и данные не изменялись.

- `docs/ai/CHARACTERS-PLATFORM.md` (v1.1) — ТЗ: текущее состояние по коду,
  целевая модель аккаунта и персонажа, активный персонаж, навигация кабинета и
  Players, карта операций и персонажной атрибуции, модель событий и публичных
  страниц, ограничения будущей галереи, этапы 0–5 с критериями приёмки,
  зависимости, риски и открытые вопросы. В v1.1 добавлены: стратегия перехода
  legacy-игровых данных `users.*` → персонаж (§3.5), безопасность и целостность
  контекста (§4.1, §4.4), границы голосования в событиях (§8.5), полное
  исключение галереи из этапа 4 (§9.6), совместимость `/players/characters`
  без обязательного 301 (§5.4).
- `docs/adr/ADR-013-account-character-separation.md` — Proposed: разделение
  аккаунта и персонажа, таблица `user_active_characters` с целостностью на
  уровне БД, `CharacterContext` + middleware, правила атрибуции, события на
  персонажа, канонические URL, перенос управления в кабинет, данные персонажа
  (раса, jobs, легенда), миграция legacy-данных (§2.10).
- `docs/adr/ADR-012-game-servers-and-characters.md` — дополнен ссылкой на
  ADR-013; решения Stage 1 Players остаются в силе.
- `docs/ai/STAGE-1-CABINET-CHARACTERS.md` (v1.0) — детальное ТЗ этапа 1:
  аудит незакоммиченного Stage 0/1 Players, маршруты и middleware кабинета,
  игровые данные персонажа (раса, main job, jobs, легенда), отдельная подфаза
  1.5 переноса legacy-данных, карта изменяемых и новых файлов, порядок
  реализации, риски и критерии приёмки.

Текущее состояние по коду: персонаж (`game_characters`) существует только как
ник + сервер + публичность (`is_public`); игровые атрибуты (раса, main job,
легенда) хранятся на аккаунте (`users.*`) как legacy; активного персонажа нет;
вся пользовательская деятельность атрибутирована аккаунту (`user_id`,
`actor_id`); участие в событиях — `unique(event_id, user_id)`; голосования и
финального утверждения состава в коде нет; публичной страницы персонажа нет.

Состояние Git на 2026-11-17: ветка `main`, HEAD `f510157`, 69 записей в рабочем
дереве (17 изменённых, 52 неотслеживаемых). **Stage 0/1 Players не закоммичен**
владельцем — это предусловие этапа 1, а не выполненный пункт.

Следующий шаг — проверка ТЗ этапа 1 (`docs/ai/STAGE-1-CABINET-CHARACTERS.md`)
владельцем и отдельное подтверждение реализации. Код этапа 1 не писался;
перенос legacy-игровых данных не применялся и требует собственного
подтверждения после отчёта `--dry-run`.

## Players Stage 0 + Stage 1 (2026-11-12) — левое меню принято; Stage 0/1 не закрыты

Stage 0 — структура раздела Players:

- `resources/views/components/players-layout.blade.php` — общая обёртка
  раздела (заголовок, левое меню, контент).
- `resources/views/players/_sidebar.blade.php` — меню разделов: Обзор /
  Мои персонажи / Сообщество. Пункт выводится только при наличии маршрута
  (`Route::has`), активный получает `aria-current="page"`.
- `resources/views/players/_section-nav.blade.php` — вертикальное боковое
  меню по прототипу `docs/design/designplayersreview.html`: бренд, две
  группы пунктов (Навигация / Мой аккаунт), футер. Пункты: Дашборд,
  Игроки, Новости игроков, События игроков, Галереи игроков, Мои персонажи,
  Личный кабинет — все ведут на существующие маршруты (`Route::has`).
  Иконки — инлайновые SVG из прототипа, цвета — из CSS-переменных темы.
- Каркас `.players-shell` (grid: колонка меню + контент) в `players-layout`;
  на экранах ≤1023px меню становится горизонтальной прокручиваемой полосой.
  Существующее меню `_sidebar` сохранено без изменений и выводится над
  контентом; вопрос объединения двух навигаций не решён.
- **Левое меню — реализовано и принято визуально (2026-11-12):** панель
  закреплена `position: fixed` между фактической нижней границей шапки и
  фактической верхней границей футера, поэтому при прокрутке основного
  контента не перемещается. Геометрия: `top` = `getBoundingClientRect()`
  `.bottom` шапки, `bottom` = `--footer-offset` (существующий
  `initFooterOffset()`),
  `left`/`width` — по колонке `.players-shell__nav`; CSS-переменные
  `--players-nav-top` / `--players-nav-left` / `--players-nav-width`
  выставляет `initPlayersNavOffset()` (`resources/js/app.js`) и
  пересчитывает через `ResizeObserver` и `resize` окна. `z-index` панели
  999, шапки и футера — 1000. Сетка раздела продолжает резервировать
  колонку меню, поэтому контент не смещается. Внутренней вертикальной
  прокрутки нет (`overflow-y: visible`, `scrollHeight == clientHeight`),
  пункты не обрезаются. На ≤1023px — `position: static`, `width: auto`,
  `min-height: 0`: горизонтальная навигация сохранена.
  Требования и критерии — `docs/designreview.md` §11.
- **Граничные ограничения (зафиксированы, не устранены):** при высоте окна
  576–594 px меню физически не помещается между шапкой и футером — карточка
  выходит за верхнюю границу футера на 64–82 px и перекрывается им; при
  существенно большем числе пунктов (тестовое меню 25 пунктов, 1311 px)
  переполнение больше. Внутренняя прокрутка и обрезка для этих случаев не
  вводились; поведение сообщается отдельно и не считается исправленным.
- **Приёмка:** визуальная приёмка владельцем (2026-11-12) — поведение левого
  меню подтверждено как соответствующее требованиям. Stage 0/1 в целом **не
  закрыты**: визуальная доводка меню, дублирование навигации (`_section-nav`
  и `_sidebar`) и остальные пункты раздела остаются открытыми.
- `players/dashboard.blade.php` и `players/directory.blade.php` переведены
  на новый layout; инлайновые стили каталога заменены классами.
- Локализация: секция `site.players.*` в `lang/{ru,en}/site.php`.
- `resources/css/app.css`: блоки `.players-shell*`, `.players-section-nav*`,
  `.players-layout`, `.players-nav*`, `.players-cards`, `.players-card*`,
  `.players-form` на существующих CSS-переменных; на мобильных меню
  становится горизонтальной полосой.

Stage 1 — игровые серверы и персонажи (ADR-012):

- Миграции `game_servers` и `game_characters`; модели `GameServer`,
  `GameCharacter`; отношение `User::gameCharacters()`.
- `GameCharacterPolicy` — владение проверяется на сервере, `create`
  требует подтверждённый email.
- `StoreGameCharacterRequest` / `UpdateGameCharacterRequest` — `user_id`
  не принимается, сервер только активный, уникальность имени без учёта
  регистра в рамках сервера с учётом soft-deleted записей.
- `MyCharactersController` (index/store/update/destroy) + маршруты
  `players.characters.*` внутри группы `auth + section.access:players +
  mfa.required`; запись — под `verified`.
- `GameServerSeeder` (12 серверов, `updateOrCreate` по `slug`), подключён
  к `DatabaseSeeder`.
- `tests/Feature/Players/GameCharacterTest.php` — 19 тестов.

Модель уникальности: имя уникально в пределах `game_server_id` и остаётся
занятым после soft delete; валидация использует `withTrashed()`, чтобы не
расходиться с уникальным индексом MySQL. Восстановление персонажа в Stage 1
не реализовано.

Ограничения: Stage 2 (Linkshell) не начат; Events и участие персонажей в
событиях не реализованы; публичной страницы персонажа нет.

Проверки (2026-11-12):

- полный Pest — 1155 passed, 4136 assertions, exit 0;
- Larastan — No errors (269 файлов), exit 0;
- Pint `--test` — PASS, 478 файлов, exit 0;
- `vite build` — успешно, exit 0. `npm run build` в `public/build` падает
  с `EACCES`: каталог `public/build/assets` принадлежит `root` (артефакты
  созданы из контейнера). Это проблема прав окружения, не кода; сборка
  проверена с альтернативным `--outDir`.

Проверки бокового меню (2026-11-12, повторно):

- целевые тесты Players — 26 passed (70 assertions), exit 0;
- Pint `--test` — PASS, 478 файлов, exit 0;
- `npm run build` — снова `EACCES` на `public/build/assets` (права `root`);
  `vite build --outDir /tmp/vite-verify` — успешно, exit 0;
- визуальная проверка через Playwright: меню присутствует на `/players`,
  `/players/directory`, `/players/characters`; активные пункты — Дашборд,
  Игроки, Мои персонажи соответственно; горизонтального переполнения на
  390px нет (`scrollWidth == clientWidth == 390`). Скриншоты:
  `docs/design/screenshots/players-{desktop-light,desktop-dark,mobile-light,characters-desktop}.png`.

Замеры закреплённой панели (2026-11-12, тестовый стенд с реальными
`resources/css/app.css` и `resources/js/app.js`, `getBoundingClientRect`):

- 1440×900, 1280×720 (100 %): `header.bottom` 56, `menu.top` 56,
  `footer.top` 633, `menu.bottom` 632 → верх 0 px, низ 1 px; при прокрутке
  (scrollY 1750/1930) координаты не меняются;
- 1440×900 при 125 % (1152×720): `menu.top` 56, `menu.bottom` 632,
  `footer.top` 633 — те же 0/1 px;
- 1024×594 и 1280×720 при 125 % (1024×576): доступная высота 450/432 px
  при содержимом 512 px → низ меню ниже верха футера на 64/82 px
  (граничное ограничение);
- 390×844: `position: static`, `min-height: 0`, горизонтальная полоса,
  `scrollWidth == innerWidth == 390`;
- внутренних прокруток нет ни в одном сценарии (`scrollables: []`),
  пункты внутри карточки (7 и 25 пунктов);
- 1440×900 при 150 % даёт CSS-viewport 960 px → срабатывает breakpoint
  ≤1023px, панель становится `static` (существующий адаптив).

Ограничение проверки: полноценная авторизованная сессия недоступна (пароль
`admin@ffxi.local` не подошёл; пользователи, роли и MFA не менялись),
поэтому `/players`, `/players/directory`, `/players/characters` измерены на
стенде, а не на авторизованных страницах. Все три страницы используют общий
`x-players-layout`, геометрия у них идентична.

## HEAD
`f510157` на ветке `main` (fix(layout): fixed footer + geometry of the
Players menu); перед ним `e602a5c` (gallery), `087cae9` (pages),
`298f5bc` (ADR-011 L4 — Filament timezone handling); `origin/main` —
`e6099e9` (локальные коммиты не запушены). ADR-011: L2 — `60b6d82`,
L3 — `d01b8d2`, L4 — `298f5bc` — реализованы и закоммичены.
E10.1 (`68f5eae`), E10.2 (`c65e1f7`), E10.3 (`b0c68d9`),
E10.4 (`5274ed5`), E10.5 (`15f084a`), E10.5b (`bf6a551`) — закоммичены.
Stage 9 закрыт `bb3bbf7`.

## Последние изменения (интеграционный коммит A: ADR-011 L5/L6 + SitePageEditor)

Аудит единого редактора страниц — исправления:

- `SitePageEditor`: публикация и `menu_order` доступны CMS и в режиме
  создания; slug-валидация (формат, `RESERVED_SLUGS`, уникальность только
  среди активных); `key()` бросает исключение при отсутствии константы
  провайдера; `saveSystem()` атомарен (файл → транзакция настроек/аудита/
  меню → удаление заменённого файла после commit; новый файл удаляется при
  ошибке); RU/EN подписи системных пунктов меню из `site.nav.*`.
- `PageMenuManager`: синхронизация `label_ru`/`label_en`/`target`,
  схлопывание дублей привязок, `menu_order` — только стартовый `order`
  новой привязки (порядок существующих принадлежит Header/Footer), аудит
  `page.created`/`page.updated`, осмысленный тумблер home (логотип-ссылка).
- `PageResource`: аудит `page.published`/`page.unpublished`.
- `SitePageRegistry`: отсутствующий классифицированный маршрут больше не
  даёт 500 (пропуск + `Log::warning` + категория `missing` на
  `/admin/routes`); `auth`-страницы классифицируются как `auth`.
- `PageEditorProfile`: `menuLabelKey`, кэш профилей по локали, удалены
  неиспользуемые `slugField`/`seoFields`/`hasBody`.
- Провайдеры системных страниц: `KEY_META_DESCRIPTION(_EN)`, вывод
  `<meta name="description">` с per-field fallback.
- `PageController`: `RESERVED_SLUGS` дополнен (`contacts`, `mfa`,
  `filament`, `storage`), защита от `livewire-` префикса
  (`hasReservedPrefix()` — `public static`, общий с редактором и генератором).
- Миграция `2026_11_10_000000_add_active_slug_key_to_pages_table`:
  generated `slug_key` + `UNIQUE`, soft-deleted страница освобождает slug.
- Новые тесты: `tests/Feature/Filament/PageEditorHardeningTest.php`.

Второй READ-ONLY аудит — устранение замечаний:

- Slug CMS: замыкающее правило `Page::isReservedSlug()` в
  `SitePageEditor::slugSection()` отклоняет префикс `livewire-`
  (`Rule::notIn` его не видит).
- Автогенерация: `Page::generateUniqueSlug()` вместо `Str::slug()` —
  учитывает `RESERVED_SLUGS` и префиксы (база получает `page-`), занятость
  активных slug (`-2`, `-3` … до 50), ограничение длины
  (`SLUG_MAX_LENGTH`). Исчерпание вариантов (`SlugGenerationException`) и
  нарушение `pages_slug_key_unique` переводятся в ошибку поля `data.slug`,
  а не в HTTP 500.
- `saveSystem()`: единый `try` от загрузки файлов до транзакции; новые файлы
  удаляются на любом пути выхода (разрешение констант, `$previous`,
  транзакция); `resolveImages()` чистит сохранённое на любой `Throwable`.
  Заменённые файлы по-прежнему удаляются только после commit.
- `<title>`/`meta description`: проверено, что XSS-вектора нет — Blade
  экранирует значение сокращённого `@section('name', $value)` при
  сохранении секции; добавлен regression-тест на 6 системных и CMS-страницу
  (escaped ровно один раз) и комментарий в layout.
- Lang: `filament.page_editor.validation.slug_taken|slug_auto`, уточнён
  `fields.slug_help`.
- Тесты: +15 сценариев в `PageEditorHardeningTest` (всего 1099 passed).

## Прогресс по этапам (§30 context.md)

| Этап | Название | Статус | Артефакты |
|---|---|---|---|
| 0 | Timeweb audit | pending | — (владелец) |
| 1 | ADR-001 stack | done | docs/adr/ADR-001-stack.md |
| 2 | Docker + skeleton | done | docker-compose.yml, /up |
| 3 | Auth, roles, foundation | done | section.access, Gate::before (scoped) |
| 4 | Content engine | done | news, pages, comments, sanitizer |
| 5 | Gallery | done | ImageProcessor, ProcessPhotoJob; RU/EN-поля, PhotoSwipe, комментарии к фото, публичные галереи игроков |
| 6 | Profiles & cabinet | ACCEPTED | docs/acceptance/stage-6.md |
| 7 | Dashboard | ACCEPTED | docs/acceptance/stage-7.md |
| 8 | Filament | ACCEPTED | docs/acceptance/stage-8.md; контракт: docs/ai/STAGE-8-CONTRACT.md |
| 9 | Backup/restore | DONE / ACCEPTED | docs/adr/ADR-004-backup-restore.md, docs/ai/STAGE-9-CONTRACT.md, docs/acceptance/stage-9.md |
| 10 | Tests (§9) | NOT STARTED — ожидает отдельного решения после локальной проверки проекта | — |
| 11 | CI | NOT STARTED | — |
| 12 | Timeweb staging | NOT STARTED | — |
| 13 | Deployment | NOT STARTED | — |
| 14 | Production | NOT STARTED | — |
| 15 | Update UI | NOT STARTED | — |

## Stage 7 — детализация

- D1 ✅ — schema + модели events (`EventType`, `Event`, `EventParticipant`).
  Миграции 000017–000019. Enum EventStatus, EventParticipantStatus.
- D2 ✅ — EventPolicy, EventService, 4 database-notifications,
  EventController, EventParticipantController, Livewire EventsBoard
  и EventSignup, EventCommentController. Миграция 000020 — activities
  (D3).
- D3 ✅ — Activity model, ActivityType, ActivityLogger, 6 событий
  + 6 listeners, ActivityController, /activity, dashboard feed.
- D4 ✅ — Chat (Livewire ChatRoom + модерация). Миграции 000021
  chat_banned_permanently, 000022 chat_messages; database notifications;
  synchronous chat activity.
- D5 ✅ — ActivityBell (community + personal database notifications),
  OnlineUsers widget, 30s/60s polling; activity snapshots скрываются,
  если существующий subject недоступен viewer'у.
- D6 ✅ — data-driven DashboardWidget grid на /players; сортировка,
  активность и column_span из БД; четыре community-типа.
- D7 ✅ — Scheduler: database event reminders за час до начала с
  проверкой актуального starts_at и идемпотентностью; retention
  activities по activity_retention_days через Artisan-команду.
- Приёмка Stage 7 ✅ — `docs/acceptance/stage-7.md`; устранено
  дублирование регистрации activity listeners.

## Stage 8 — детализация

- E1 ✅ — Panel navigation (4 группы) + AdminDashboard со статистикой,
  графиком регистраций за 30 дней, audit preview и shortcuts.
- E2 ✅ — UserResource, RoleResource, GuestResource; suspended account
  workflow (миграция 000024) + account requests UI.
- E7 ✅ — SettingsPage (8 групп), `App\Rules\IpAllowlist`,
  `SettingsRepository::setMany` в транзакции, `admin_2fa_required=false`
  и `admin_ip_allowlist=[]` по умолчанию, re-auth при изменении allowlist.
- E3 ✅ — PermissionsMatrixPage: роли × `section.*.view` + `guest_sections`,
  немедленный cache flush, admin-only.
- E4 ✅ — NewsResource + CommentResource: MarkdownEditor → единый
  `ContentRenderer`/`HtmlSanitizer`, plain-text комментарии, модерация.
- E5 ✅ — GalleryResource (+PhotosRelationManager), PageResource,
  EventResource (через EventService).
- E6 ✅ — EventTypeResource (admin-only gate), RankResource,
  DashboardWidgetResource, ActivityLogResource (read-only, admin-only).
- E8.1 ✅ — MFA opt-in: миграция 000023, `AppAuthentication` в панели,
  `EnsureAdminMultiFactorAuthentication`.
- E8.2 ✅ — `EnsureAdminIpAllowed` (пустой список = allow-all).
- E8.3 ✅ — `LogsAdminActivity` + `AdminActivityLogger` + провайдер
  (`RecordCreated`/`RecordUpdated`, DeleteAction/DeleteBulkAction).
- E8.4 ✅ — `ReAuthenticateAction` для необратимых действий.
- E8.5 ✅ — Request workflow: restore (только статус), re-auth hard delete
  без каскада, фильтр «Запросы».
- Fix ✅ — `email_verified_at` добавлен в `#[Fillable]` `User`.
- Fix ✅ — R4 закрыт: смена ролей логируется как `user.roles_changed`
  (`EditUser` before/after save; тесты `UserRoleAuditTest`).
- E9 ✅ — acceptance gap-fill (15 тестов) + `docs/acceptance/stage-8.md`.
- Все отклонения R1–R7 закрыты; Stage 8 — ACCEPTED.

## Stage 9 — детализация

- E9.0 ✅ — ADR-004 (backup / restore / rollback) + STAGE-9-CONTRACT.md
  + CHANGELOG.md (минимальный) + sync docs.
- E9.1 ✅ — backup core: `BackupManifest`, `DatabaseDumper`,
  `FilesArchiver`, `BackupStorage`/`LocalBackupStorage`, `BackupLock`,
  `BackupService`, `config/backup.php`, `storage/backups/`.
- E9.2 ✅ — CLI `app:backup` + `app:backup-cleanup` (каркас) + Makefile
  (`make backup`) + `default-mysql-client` в dev Dockerfile.
- Amendment ADR-004 §3.1 — `lang/` включён в Scope B.
- E9.3 ✅ — retention GFS 7/4/12 (`BackupRetention`), реальный
  `app:backup-cleanup` (под backup-lock), free-space safety
  (`FilesArchiver::estimateSize` + `min_free_space_pct`), settings-ключи
  `backup_retention_{daily,weekly,monthly}` и `min_free_space_pct`.
- E9.4 ✅ — Filament `BackupPage` (admin-only, `/admin/backups`):
  таблица backup'ов (array-records), создание (radio mode/scope + Scope B
  warning), просмотр/download manifest, удаление, retention settings;
  audit `backup.created`/`backup.deleted` без IP (contract §12). Restore —
  только CLI (R4); restore-request flow — E9.5 (§2).
- E9.5 ✅ — restore: миграция/модель `restore_requests` (UUID, HMAC-SHA256,
  expiry 30 мин), `RestoreRequestService`, `RestoreService` (read-only
  `check` + `apply`: lock, auto-backup, maintenance, DB→files, smoke-test,
  audit), `app:rollback LIST/CHECK/APPLY`, `app:restore-test` (каркас),
  `make rollback`, restore-request flow в `BackupPage` (re-auth + MFA
  панели, admin-only). Audit `restore.requested/applied/failed/rejected`
  с IP (security-события). HTTP restore отсутствует (R4).
- E9.6 ✅ — Scheduler (`bootstrap/app.php`): `04:00` DB-only backup
  (`app:backup --mode=db --triggered-by=cron`) + `04:30` retention cleanup
  (`app:backup-cleanup`), оба `withoutOverlapping()`; feature-тест
  `tests/Feature/Scheduler/BackupScheduleTest.php`.
- E9.7 ✅ — `app:restore-test` (реализация) + `make restore-test`:
  `RestoreTestService` восстанавливает backup в изолированную dev-БД
  `laravel_ffxi_restore_test` (integrity-проверка `hash_db` → DROP/CREATE →
  dump → smoke-test → DROP в `finally`), не затрагивая рабочую БД;
  guard на совпадение с рабочей БД; `config/backup.php` блок `restore_test`.
- E9.8 ✅ — приёмка: `docs/acceptance/stage-9.md`; закрыты пробелы
  audit-событие `backup.cleanup` (§12/§13.22) и тест провала совместимости
  (§13.15); `RestoreTestService` добавлен в карту компонентов контракта §3.1;
  синхронизированы статусные документы.

## Stage 9 — открытые gaps

- **Q6** — внешняя копия backup (S3/restic/вручную) не реализована
  (out of scope Stage 9, ADR-004 §8.2). Точка расширения — `BackupStorage`.
- Рассинхронизация migrations при restore старого dump — известный риск
  (§6.4 п.4), вне scope Stage 9.

## Frontend — брендинг (логотипы + favicon)

- Логотип в навбаре — `public/img/bx_default_logo.gif` в общем layout
  (`resources/views/layouts/app.blade.php`), все публичные страницы; контейнер
  `.logo-wrap` + рамка + оверлей на `[data-theme="dark"]` (токены палитры,
  footprint 40px — шапка не выросла).
- Логотип на главной — `public/img/2a.png` в `resources/views/home.blade.php`
  через `.logo-wrap` (`max-height: 80px`, `border-radius: 4px`) — тот же паттерн.
- Favicon — `public/favicon.ico` (0 → 1150 байт, рабочий ICO): публичный layout
  (`asset('favicon.ico')`) + админка Filament (`AdminPanelProvider`
  `->favicon(asset('favicon.ico'))`).
- Tests: 779 passed (2486 assertions).
- **Open issue:** мелькание favicon phpMyAdmin в браузере — в dev-контуре причина
  вне проекта (phpMyAdmin не на origin сайта; одна декларация `<link rel="icon">`;
  нет preload/prefetch/service worker). Cache-busting не применялся. См.
  `context.md` §38.
- **Хвост:** две косметические правки в `resources/css/app.css`
  (`body > header` → `body>header` и пустая строка после
  `@media (max-width: 767px) {`) — не по задаче, внешний источник, семантически
  нейтральны; включены в коммит как есть. Откатить отдельной задачей.

## News — редактор шапки страницы `/news`

> **Устарело:** `App\Filament\Pages\NewsPage` и маршрут `/admin/news-page`
> удалены. Шапка новостей редактируется единым `SitePageEditor`
> (`/admin/pages/sys.news.index/edit`). Раздел сохранён как история этапа.

- Админка: `App\Filament\Pages\NewsPage` (`/admin/news-page`), в сайдбаре
  скрыта. Точка входа — header action «Редактор страницы» в `ListNews`
  (рядом с «Создать»), виден только admin.
- Хранение: `settings` + `SettingsRepository`, без миграций и моделей.
  Ключи `news_page.*` (`meta_title`, `h1`, `body`, `main_image`,
  `main_image_width`, `main_image_height`); отсутствие ключа или `null` =
  дефолт (`Новости игры`, `img/ogp_eu.webp`, 1200×630, два прежних абзаца).
- `app/Services/NewsPageContent.php` (новый): `defaults()`, `editable()`,
  `view()`. `HomeImageUploader` расширен слотом `news_page` (карта
  `dir`/`suffix`/`max`): `storage/app/public/news/header/{hash}_header.webp`,
  WebP q85, `scaleDown` до 1920×1080 без апскейла.
- Форма: Title, H1, Картинка шапки (`FileUpload` + тумблер сброса), Текст
  (`MarkdownEditor`). Сохранение пишет `admin_audit_logs`
  (`news_page.updated`).
- Фронт: `NewsController::index()` → `newsPage`; шапка `news/index.blade.php`
  из настроек, классы и порядок блоков сохранены, цикл и пагинация не
  тронуты. Текст — `ContentRenderer`; пустой текст скрывает `.content-text`.
- Docs: `frontend-spec.md` 1.8 (§6.4.2, §7.3, §9 сценарий 30),
  `adminreview.md` §6.1, `CHANGELOG.md`.
- Проверено: `php -l`, `git diff --check`; `/news` при дефолтах — текст
  шапки совпадает с прежним после нормализации пробелов, 5 карточек и
  пагинация идентичны; сохранение 1600×900 → WebP, отдача 200 `image/webp`;
  сброс удаляет файл и возвращает 1200×630. Тестовые ключи, файлы и записи
  аудита после проверки удалены.

## Home — редактор Главной страницы (singleton)

> **Устарело:** `App\Filament\Pages\HomePage` и маршрут `/admin/home`
> удалены. Главная редактируется единым `SitePageEditor`
> (`/admin/pages/sys.home/edit`). Раздел сохранён как история этапа.

- Админка: `App\Filament\Pages\HomePage` (`/admin/home`, группа «Контент»,
  `navigationSort = 0` — выше «Новости»). Filament 5 не умеет
  singleton-ресурсы, поэтому страница кастомная (по паттерну `SettingsPage`):
  без списка, сразу форма. Доступ — только `admin`.
- Хранение: `settings` + `SettingsRepository`, без миграций и моделей. Ключи
  `home.meta_title`, `home.h1`, `home.body`, `home.hero_logo`,
  `home.main_image`, `home.main_image_width`, `home.main_image_height`;
  отсутствие ключа или `null` = встроенный дефолт.
- `app/Services/HomePageContent.php` (новый): `defaults()`, `editable()`,
  `view()`. `app/Services/HomeImageUploader.php` (новый): слоты `hero`/`image`,
  MIME-проверка JPEG/PNG/WebP, ≤ 4 МБ, ≤ 25 Мп, min сторона 100 px, WebP q85,
  `scaleDown` без апскейла, `home/{slot}/{hash}_{slot}.webp`.
- Форма: Title, H1, Hero-логотип, Основное изображение, Текст
  (`MarkdownEditor`). Тумблеры «вернуть стандартное» видны только при
  загруженном файле, удаляют файл и сбрасывают ключи. Сохранение пишет
  `admin_audit_logs` (`home.updated`).
- Фронт: `home.blade.php` → `HomePageContent::view()`; классы и структура
  сохранены, для стандартного логотипа разметка не изменилась, для
  загруженного — `logo-wrap--custom` + нейтральные правила в `app.css`.
  Текст — `ContentRenderer`; пустой текст скрывает блок.
- Docs: `frontend-spec.md` 1.7 (§6.15, §7.3, §9 сценарий 29),
  `adminreview.md` §5, `CHANGELOG.md`.
- Проверено: `php -l`, `git diff --check`; `/` при дефолтах совпадает с
  прежним hero-блоком; 1600×900 → 1287×724 WebP, 415×100 без апскейла,
  800×450 → `width="800" height="450"`; GIF/SVG/60×60/14.5 МБ отклонены;
  сброс удаляет файлы. Тестовые ключи и записи аудита после проверки удалены.

## News — обложки новостей

- Баг «обложка не отображается»: `NewsCoverUploader::MIN_SIDE` (200)
  отклонял файл `22822.jpg` (415×100) → `ImageProcessingException::tooSmall`,
  `cover_path` оставался `NULL`. Порог снижен до 100.
- `NewsCoverUploader`: без апскейла — `scaleDown()` вместо `cover()`, если
  источник меньше 1280×720; результат всегда WebP q85 в
  `storage/app/public/news/covers/{user_id}/`.
- `news/show.blade.php`: порядок заголовок → обложка → метаданные → текст
  (раньше обложка была над `<h1>`, а метаданные — над обложкой).
- `news/index.blade.php`: обложка убрана из ленты.
- `resources/css/app.css`: добавлено правило `.news-image` (отсутствовало).
- `NewsResource`: `FileUpload` ограничен JPEG/PNG/WebP (`acceptedFileTypes`).
- Docs: `frontend-spec.md` 1.6 §6.4.1 (форматы, хранение, URL, чек-лист
  диагностики).
- Проверено: детальная страница id=16 — `<h1>` → `<img>` → `.content-text`;
  обложка HTTP 200 `image/webp`; лента `/news` — 0 вхождений `storage/news`;
  PNG и WebP загружаются и отдаются.
- Тесты не запускались и не добавлялись — отдельный этап.

## News — редакционная дата `published_at`

- `published_at` — редакционная дата (дата анонса источника), а не момент
  выхода на сайте. Будущее/прошлое на видимость не влияет; видимость =
  `status = published` + `published_at NOT NULL`; отложенной публикации нет.
- `app/Models/News.php`: `isPublished()` / `scopePublished()` без проверки
  «дата не в будущем»; `scopePinnedFirst()` + `orderByDesc('id')`
  (tiebreaker).
- `app/Services/NewsPublishedAtResolver.php` (новый): только дата →
  `00:00:00`; при наличии новостей на дату → `max + 5 мин`; точное время
  как есть; при редактировании без изменения даты — без сдвига.
- `NewsResource`: `DateTimePicker` → `native(false)`, `displayFormat('d.m.Y
  H:i')`, `required` при `published` + `validationMessages(['required' =>
  'Укажите дату новости'])`.
- `CreateNews` / `EditNews`: автоподстановка `now()` удалена, вызов
  резолвера. «Опубликовать»/bulk по-прежнему используют `now()`.
- Фронтенд: `/news` и `/news/{slug}` — только `d.m.Y`, без времени.
- Docs: `frontend-spec.md` 1.5, `context.md` 4.0.5, `adminreview.md` §6,
  `CHANGELOG.md`.
- **TODO:** кабинет (`my-news.blade.php` — `H:i`, `MyNewsController` —
  `now()`). Таймзона — архитектурно закрыта ADR-011: L1 (поля и профиль)
  — DONE / ACCEPTED; отображение дат — этапы L2–L6, см. раздел
  «Локализация и часовые пояса». Страницы «Главная» и «Новости» —
  в параллельной доработке владельцем (WIP, не баг).
- Тесты не запускались и не добавлялись — отдельный этап.

## Контракты, которые НЕЛЬЗЯ менять

- Roles: только user / editor / admin. Никаких owner/moderator/super_admin.
- section.access + guest_sections для матрицы доступа (ADR-007).
- Gate::before для admin — только для section.* (ADR-007).
- Канал уведомлений Stage 7: database-only (ADR-008 §2.6).
- Chat moderation: chat.moderate (admin only, ADR-008 §2.5).
- Mute: chat_banned_until + chat_banned_permanently (решение владельца для D4).
- Retention activity: 180 дней от created_at (ADR-008 §2.3).
- api_chart / html_board — Stage 8 (ADR-008 §2.8).
- Пакеты: только из context.md §3 (уже установлены).

## Известные gaps (зафиксированы, не блокеры)

См. docs/acceptance/stage-6.md §5 и docs/acceptance/stage-8.md §4.

## MFA — ADR-009 / E10 (E10.1–E10.4; implementation in progress)

- `docs/adr/ADR-009-unified-mfa.md` — **Proposed / awaiting implementation**:
  unified site-wide MFA; глобальный gate `mfa_global_enabled` (default true);
  единый challenge `/mfa/challenge`; escape-hatches `/cabinet/security`,
  `/admin/settings`; verification до logout; storage/compat Filament; R5-amendment;
  enforcement-матрица по маршрутам (§2.3); permission `mfa.manage` (admin-only,
  MFA reset); TOTP/QR-стек без `bacon`.
- `docs/ai/E10-MFA-CONTRACT.md` — пофазный план E10.1–E10.8 + тестовая матрица (T1–T24).
- **Прогресс реализации:**
  - E10.2 ✅ — escape-hatch `/admin/settings` (`c65e1f7`).
  - E10.3 ✅ — MFA setup `/cabinet/security` (`b0c68d9`; self-service setup/confirm/
    disable/regenerate/cancel; Blade + POST; только свой аккаунт; re-auth для
    disable и regenerate; audit `mfa.enabled`/`mfa.disabled`).
  - E10.4 ✅ — unified challenge `/mfa/challenge` + middleware `mfa.required`
    (`RequireMfa`): enforcement для `/players` и admin-панели (persistent, покрывает
    Livewire); session-ключи `mfa_verified_user_id`/`mfa_verified_at` (binding к user,
    T24); global gate; escape-hatches; editor `/admin/profile` skip; rate limit 5/мин;
    audit `mfa.challenge_success`/`mfa.challenge_failure`. Легаси-middleware
    `EnsureAdminMultiFactorAuthentication` сохранён, но shadowed (удаление — E10.6).
  - E10.5 ✅ — admin MFA management (permission `mfa.manage`, global toggle
    `mfa_global_enabled`, список MFA-статуса пользователей, per-user reset через
    `ReAuthenticateAction`, audit `mfa.admin_reset`, email
    `MfaResetByAdminNotification`).
  - E10.5b ✅ — production MFA invariant (`MfaPolicy`, fail-closed): в production
    MFA для admin обязательна и не ослабляется ни через настройки, ни через UI
    (`SettingsPage` UI-lock + server-side guard); environment — только hardening,
    без нового env-флага; dev (`admin_2fa_required=false`) — вход по паролю.
- **Tests:** 779 passed (2486 assertions) после E10.5b.
- E10.1 (2026-10-08) закрыл пункты аудита E10-DOCS D1–D8 в документации:
  `mfa.manage` в `frontend-spec.md` §3.3/§7.6; синхронизация статусных документов;
  конкретная enforcement-матрица; TOTP/QR без composer-change; устранение Filament
  profile MFA UI в E10.6; audit-API и расхождение `AuditLogger` зафиксированы.
- **Enforcement-матрица (D5) закрыта:** `/players` — единая защищаемая область
  (`players.dashboard`, `players.directory`, `players.show`); editor
  `GET /admin/profile` вне матрицы; Filament setup-route нейтрализуется в E10.6.
- **Расхождение `AuditLogger`** (security-specific IP: `STAGE-8-CONTRACT` §3.3 vs
  текущий default `recordIp = true`) — отдельный тех-долг Stage 8; закрыть до E10.7,
  если потребуется изменение.
- **Runtime state:** в dev-БД `admin_2fa_required = false` (2026-10-08, для ручной
  приёмки); `mfa_global_enabled` — default `true` (запись появляется сидером).
  В production effective policy форсирует оба инварианта независимо от БД.
- Порядок: E10.1 → E10.2 → E10.3 → E10.4 → E10.5 → E10.5b → E10.6 → E10.7 → E10.8.

## Локализация и часовые пояса — ADR-011 (L1 DONE / ACCEPTED; L2–L4 DONE; L5/L6 DONE — ACCEPT WITH REMARKS, pending owner review; L7 отложено)

> **Приёмка L5/L6:** `docs/acceptance/adr-011-l5-l6.md` —
> **ACCEPT WITH REMARKS — pending owner review**. Документ подтверждает
> техническую готовность; 22 критерия §10 ADR-011 проверяет владелец
> проекта. Полный статус `Accepted` не присвоен.

- `docs/adr/ADR-011-localization-and-timezones.md` — **Partially accepted**:
  L0 — DONE; **L1 — DONE / ACCEPTED (2026-11-05)**; **L2 — DONE
  (`60b6d82`)**; **L3 — DONE (`d01b8d2`)**; **L4 — DONE (`298f5bc`)** —
  реализованы и закоммичены, без формальной приёмки; L5 — IN PROGRESS
  (L5.0–L5.1 DONE в рабочем дереве); L6 — NOT STARTED (после L5);
  L7 — отложено.
  Архитектура: независимость языка интерфейса и часового пояса; три
  семантики времени (календарная дата / локальный момент / международное
  UTC); пояс: `users.timezone` → cookie `tz` (IANA браузера) → `UTC`;
  язык: `users.locale` → сессия → cookie `locale` → `Accept-Language` → `ru`;
  `app.timezone = UTC` неизменен; аудит и backup — всегда UTC.
- **L1 — реализовано и принято:**
  - миграции `0001_01_01_000025_add_locale_timezone_to_users_table.php`
    (`users.locale` `varchar(16) null`, `users.timezone` `varchar(64) null`)
    и `0001_01_01_000026_add_timezone_to_events_table.php`
    (`events.timezone` `varchar(64) null`); без default и backfill;
  - `App\Enums\Locale`, `App\Services\TimezoneOptions`,
    `App\Services\LocalePreference` (сначала БД, затем session),
    `UpdateLocalePreferenceRequest`, `UpdateTimezonePreferenceRequest`,
    `PreferencesController`;
  - маршруты `POST /cabinet/preferences/locale` и
    `POST /cabinet/preferences/timezone` (группа `auth` + `verified`);
  - профиль кабинета — две независимые формы; админка — поля `locale` и
    `timezone` в `UserResource`; `lang/ru/filament.php`;
  - `UpdateProfileRequest` и основная форма профиля не изменялись;
  - тесты L1: 40 passed / 172 assertions; полный Pest — 816 passed,
    5 failed (предсуществующее расхождение с ADR-010, к L1 не относится).
- **Принятые ограничения P1–P8** (не исправлялись, зафиксированы в
  ADR-011 §9, L1.1): отсутствие явной проверки в `LocalePreference`;
  прямое присваивание полей модели; блокировка сохранения формы Filament
  при невалидных сохранённых значениях; `Locale::from()` вне `try`;
  расхождение перечня файлов L1; неполное покрытие ветки `forget`;
  `old()` после ошибки БД; стоимость построения списка поясов.
- **L2 — DONE (`60b6d82`), реализовано и закоммичено:**
  - `App\Services\LocaleResolver` — auth: `users.locale` → session → cookie
    `locale` → `Accept-Language` → `ru`; гость: session → cookie →
    `Accept-Language` → `ru`; невалидные значения игнорируются; вне HTTP
    (CLI, queue, scheduler) — `ru`;
  - `App\Services\TimezoneResolver` — `users.timezone` → cookie `tz` → `UTC`,
    `isFallback()`; валидация через `TimezoneOptions::isValid()`; session не
    используется; вне HTTP — `UTC`; зарегистрирован singleton, чтобы
    `resolve()` и `isFallback()` описывали одно разрешение (D2 аудита);
  - `App\Http\Middleware\SetLocale` — подключён в `web` после
    `IdentifyGuest`; `SetAdminPanelLocale` переведён на общий резолвер
    (остаётся в `persistentMiddleware`, жёсткий `ru` убран);
  - `App\Http\Controllers\LocaleController` + `POST /locale`
    (`locale.update`, `throttle:30,60`): гость — session + cookie; auth —
    `users.locale` → session → cookie; при ошибке записи session и cookie
    не меняются;
  - `App\Support\LocaleCookie` — plain-cookie `locale` (1 год, path `/`,
    `SameSite=Lax`, `HttpOnly`); «Авто» в профиле удаляет cookie;
  - `bootstrap/app.php` — `encryptCookies(except: ['cookie_banner_ack',
    'locale', 'tz'])`;
  - `resources/js/app.js` — cookie `tz` из
    `Intl.DateTimeFormat().resolvedOptions().timeZone` (1 год, plain, без
    перезагрузки);
  - `resources/views/components/language-switcher.blade.php` — POST-форма
    без JS, встроена в `.header-right`; `<html lang>` из локали;
    CSS-блок `.locale-switcher`;
  - `lang/en/filament.php` — английский аналог `lang/ru/filament.php`
    (388 ключей, структура совпадает); `lang/ru/filament.php` не изменялся;
  - `PreferencesController` — сообщения для `locale` актуализированы;
    сообщения `timezone` оставлены до L3;
  - тесты: `tests/Feature/Localization/{LocaleResolutionTest,
    LocaleSwitchTest, TimezoneResolutionTest}.php` — 36 тестов /
    159 assertions; `tests/TestCase.php` объявляет `Accept-Language: ru-RU`
    по умолчанию (тестовый клиент Symfony подставляет `en-us`), чтобы
    Filament-тесты оставались детерминированными;
  - полный Pest — 852 passed, 5 failed (те же предсуществующие падения
    ADR-010); Pint — 1 предсуществующая ошибка (`HomeImageUploader.php`);
    Larastan — 1 предсуществующая ошибка (`NewsPage.php:186`);
  - `app.timezone` (`UTC`), `.env`, миграции, MFA, роли и доступ к разделам
    не изменялись.
- **В dev-БД** миграции L1 применены адресно (`--path`), обычный `migrate`
  не запускался; 5 существующих пользователей — `locale`/`timezone` `NULL`,
  данные не переписывались; `restore_requests` не затронута (её миграция
  по-прежнему Pending).
- **Документация:** `frontend-spec.md` 2.0.1 (новый §6.16, включая §6.16.10
  «Охват локализации RU/EN»; преамбула синхронизирована с L1),
  `context.md` 4.1.1 (§5, §13, §14),
  `adminreview.md` (§6, §6.1, §11, §13),
  `privacy-policy-draft.md` §5.1, `CHANGELOG.md`.
- **Уточнения владельца (2026-11-04):** `users.locale` — `varchar(16)`;
  приоритеты языка: auth — `users.locale` → session → cookie →
  `Accept-Language` → `ru`, гость — session → cookie → `Accept-Language` →
  `ru`; переключение авторизованным успешно только после записи
  `users.locale`, затем обновляется сессия (при ошибке записи сессия не
  меняется, показывается ошибка), гостем — сессия и cookie; DST-правила
  для событий (несуществующее и неоднозначное локальное время
  отклоняются); события с `timezone = NULL`
  — только UTC; локальное время события — всем при валидном поясе; формат
  EN-даты `Nov 04, 2026`.
- **Отменено:** `settings.timezone_display.ru` / `.en` и определение пояса
  по локали. `timezone_display` — legacy, не удаляется до проверки всех
  читателей.
- **Этапы:** L0 документация — DONE → **L1 миграции и профиль — DONE /
  ACCEPTED** → **L2 резолверы и cookie — DONE (`60b6d82`)** →
  **L3 компоненты и разделы — DONE (`d01b8d2`)** → **L4 Filament
  (`FilamentTimezone` + UTC-pin) — DONE (`298f5bc`)** → **L5 полная
  локализация RU/EN — DONE (L5.0–L5.9)** → **L6 CMS и админ-инструменты —
  DONE (L6.1–L6.5)**. L7 (URL-префиксы) — отложено.
  L2–L4 реализованы и закоммичены, но не имеют формальной приёмки:
  критерии §10 ADR-011 проверяются в L6.
- **Открытые вопросы:** нет — вопросы ADR-011 §8 закрыты владельцем
  2026-11-04.

### L5/L6 — документальное закрепление требований (2026-11-06)

Результаты READ-ONLY аудита и согласованные решения зафиксированы
в ADR-011 (новые §2.16–2.25, обновлённые §5, §6, §7, §9, §10).
L5 — DONE (L5.0–L5.9); L6 — DONE (L6.1–L6.5) по объёму работ L6.

**Важно:** статус L6 — DONE относится к объёму работ L6 (CMS-навигация,
админ-инструменты, локализация). Позднее владелец отдельно согласовал новое
целевое поведение глобального футера — `position: fixed; bottom: 0`
(`docs/designreview.md` §4, §10; ADR-011 §2.23). Это требование **не входит
в объём L6** и реализовано отдельно коммитом `f510157`; статус L6 — DONE
к нему отношения не имеет.

**L5 — RU/EN локализация интерфейса (подэтапы):**

| Подэтап | Цель | Зависимости |
|---|---|---|
| L5.0 | Инфраструктура переводов: `lang/{ru,en}/site.php`, `validation.php`, `auth.php`, `passwords.php`, `pagination.php`; `fallback_locale=ru`; проверка полноты EN-ключей | L2 |
| L5.1 | Layout, Header, Footer, cookie-banner, пагинация | L5.0 |
| L5.2 | Auth, регистрация, сброс пароля, валидация | L5.0 |
| L5.3 | News и Gallery | L5.0 |
| L5.4 | Cabinet и пользовательские настройки (устаревшие тексты PreferencesController исправляются здесь) | L5.0 |
| L5.5 | Events, Chat, Activity Feed, trans_choice | L5.0 |
| L5.6 | Privacy/Cookie (добавить locale/tz в /cookie), страницы ошибок 403/404/419/429/500 | L5.0 |
| L5.7 | Filament: навигационные группы (ленивый резолв), lang/{ru,en}/filament.php | L5.0 |
| L5.8 | Carbon::setLocale(), DateFormat::localDayLabel(), относительное время, ActivityType | L5.0–L5.7 |
| L5.9 | Уведомления (16 файлов), сервисные исключения, оставшиеся PHP-строки | L5.0–L5.8 |

**Прогресс L5 (интеграционный коммит A; ранее — рабочее дерево):**

- L5.0 — DONE: `lang/{ru,en}/{auth,pagination,passwords,validation,site}.php`;
  `config/app.php` fallback `ru`; `.env.example` и `phpunit.xml`
  `APP_FALLBACK_LOCALE=ru`; `Locale::label()` и сообщение
  `LocaleController` через `__()`; `app:translations:check`
  (`TranslationCompleteness`) + тесты.
- L5.1 — DONE: Header, Footer, cookie-banner, language-switcher,
  пагинация через `lang/{ru,en}/site.php`; тесты
  `tests/Feature/Localization/PublicLayoutTranslationTest.php`.
- L5.2 — DONE: auth-страницы (login, register, forgot/reset password,
  verify email, registration closed), flash/ошибки `LoginController`,
  `PasswordResetController`, `EmailVerificationController`,
  `RegistrationOpen`, `RegisterRequest`; тесты
  `tests/Feature/Localization/AuthTranslationTest.php`.
- L5.3 — DONE: News (index/show) и Gallery (index/album/photo),
  компоненты `x-datetime`, `x-naive-date`, пустые состояния; тесты
  `tests/Feature/Localization/ContentPagesTranslationTest.php`.
- L5.4 — DONE: Cabinet (все вкладки), MFA challenge, FormRequest'ы
  кабинета, `PreferencesController`, `SocialLinkController`,
  `SectionAccess`; тесты
  `tests/Feature/Localization/CabinetTranslationTest.php`.
- L5.5 — DONE: Events, Chat, Activity Feed, `trans_choice` для счётчиков
  (участники, онлайн, повторения, фото), `EventDateInput`, `EventService`,
  `ChatRoom`; тесты
  `tests/Feature/Localization/EventsChatActivityTranslationTest.php`.
- L5.6 — DONE: `/privacy` и `/cookie` переведены, в таблицу cookie
  добавлены `locale` и `tz` (1 год) и `cookie_banner_ack`; созданы
  `resources/views/errors/{layout,403,404,419,422,429,500}.blade.php`
  (419 — перезагрузка, 429 — таймер по `Retry-After`), стили
  `.error-page`; тесты
  `tests/Feature/Localization/PrivacyCookieErrorTranslationTest.php`.
- L5.7 — DONE: `lang/{ru,en}/filament.php` (навигация, дашборд, настройки,
  ресурсы, backup, matrix, MFA, reauth); навигационные группы переведены
  на стабильные ключи (`main/community/content/system`) с ленивым
  резолвом подписей в `AdminPanelProvider`; убраны последние русские
  строки из `NewsResource` и виджета аудита; тесты
  `tests/Feature/Localization/FilamentTranslationTest.php`
  (+ обновлён `Stage8AcceptanceTest` под ключи групп).
- L5.8 — DONE: `Carbon::setLocale()` в `SetLocale`,
  `SetAdminPanelLocale` и `LocaleController`; `DateFormat::localDayLabel()`
  через `site.common.today/yesterday` (с учётом переданной локали);
  `ActivityType::label()` через `site.activity.types.*`; проверены
  plural-формы RU/EN; тесты
  `tests/Feature/Localization/DateTimeActivityTranslationTest.php`
  и обновлён `tests/Unit/DateFormatTest.php`.
- L5.9 — DONE: 5 mail-уведомлений переведены через трейт
  `Notifications\Concerns\LocalizesMail` (язык получателя, fallback — `ru`);
  `ImageProcessingException` и префиксы ошибок
  `Avatar/HomeImage/NewsCover/ImageProcessor` переведены через
  `site.images.errors.*`; `app:create-admin` использует
  `site.console.create_admin.*`; тесты
  `tests/Feature/Localization/NotificationsImagesTranslationTest.php`.
- L6.1 — DONE: CMS-навигация Header/Footer. `SiteNavigation` хранит
  `header.items` / `footer.items` (JSON) и `footer.text_ru` / `footer.text_en`
  в `settings`; типы целей `route|page|url` с allowlist маршрутов и
  проверкой схемы внешних URL; fallback подписей и текста футера EN→RU.
  Страницы админки `HeaderPage` (`/admin/header`, sort 5) и `FooterPage`
  (`/admin/footer`, sort 6) в группе «Контент» с Repeater (порядок,
  видимость, RU/EN подписи, иконка); доступ — `pages.manage` (admin+editor).
  Иконки — слот `nav` в `HomeImageUploader` (≤400×400, min 16 px, WebP q85).
  CSS: `flex-wrap` для `.footer-content`/`.footer-links`, стили
  `.nav-link__icon`. Тесты
  `tests/Feature/Localization/SiteNavigationTest.php` (15).

  *Позиционирование футера — историческая запись.* На момент L6.1 футер был
  реализован как `position: sticky` с динамической высотой вместо
  `position: fixed` и фиксированного `--footer-height`. Это описание
  **прежней реализации**, а не целевого поведения. Позднее владелец
  отдельно согласовал новое целевое поведение: глобальный футер —
  `position: fixed; bottom: 0`, постоянно виден у нижнего края окна на
  коротких и длинных страницах, высота определяется содержимым CMS,
  страница резервирует место под фактическую высоту
  (`docs/designreview.md` §4, §10; ADR-011 §2.23). Новое требование
  **реализовано** коммитом `f510157` (футер `position: fixed; bottom: 0`,
  резерв `body` через `--footer-offset`, измеряемый `initFooterOffset()`);
  статус L6.1 к этому изменению отношения не имеет.
- L6.2 — DONE: RU/EN CMS для Pages. Миграция
  `0001_01_01_000027_add_localized_columns_to_pages_table` добавляет
  nullable `title_en`, `body_en`, `body_html_en`, `meta_title_en`,
  `meta_description_en` (без backfill); `Page::booted()` рендерит
  `body_html_en` через `ContentRenderer` при изменении `body_en`.
  `PageController::show` применяет per-field fallback EN→RU (заголовок,
  HTML-тело, meta title/description); layout выводит
  `<meta name="description">`. В `PageResource` — вкладки RU/EN для
  контента и SEO. `SiteNavigation::headerItems()` добавляет опубликованные
  страницы с   `show_in_menu` (по `menu_order`, без дублей с явными
  пунктами и без зарезервированных slug'ов). Тесты
  `tests/Feature/Localization/PagesContentTranslationTest.php` (6).
- L6.3 — DONE: переключатель языка в админке через
  `PanelsRenderHook::USER_MENU_BEFORE` (`AdminPanelProvider::renderHook()`),
  Blade-вью `filament/components/language-switcher` — обычная POST-форма на
  существующий `locale.update` (`LocaleController` → `LocalePreference`),
  без второго источника истины; `users.locale` пишется, панель
  перерисовывается на выбранном языке. Тесты
  `tests/Feature/Localization/FilamentLanguageSwitcherTest.php` (4).
- L6.4 — DONE: три циферблата Dashboard ClockWidget (UTC / устройство / регион).
  `users.secondary_timezone` (nullable, varchar 64, без default/backfill) —
  третьи часы; миграция `0001_01_01_000028` была **Pending** (столбец отсутствовал),
  применена изолированно (`--path`); `TimezoneResolver::browserTimezone()` — cookie
  `tz` для начального серверного рендера часов устройства; Alpine `setInterval(1000)`
  с `Intl.DateTimeFormat` для тика и смещения; DST учитывается динамически;
  inline-селект региона в виджете (`InteractsWithForms`), валидация IANA-зоны,
  сохранение текущему пользователю, сброс; `secondary_timezone` убран из
  `EditProfile` — единственный интерфейс — виджет. `TimezoneOptions::grouped()` и
  `options()` кэшируются на 1 час (Cache::remember, ключ включает PHP_VERSION +
  hash идентификаторов); ADR-011 R15 «419 подписей на рендер» устранено (8x
  ускорение); `ClockWidget` Select использует `grouped()` (optgroup, 11 регионов,
  419 IANA-зон). Виджет выводится **один раз** — через `discoverWidgets()` панели
  в основной сетке; `AdminDashboard::getHeaderWidgets()` удалён, поскольку давал
  второй независимый экземпляр (header + сетка). Обнаружено и устранено
  усечение селектора: дефолтный `optionsLimit` Filament (50) показывал только
  первые африканские зоны — поднят до `TimezoneOptions::count()`; поиск переведён
  на серверный (`getSearchResultsUsing` → `TimezoneOptions::search()`), где `_`
  и пробел эквивалентны, поэтому находятся и `America/Argentina/Buenos_Aires`,
  и «Buenos Aires»; подпись — `Europe/Helsinki — UTC+02:00`. Тесты
  `tests/Feature/Localization/ClockWidgetTest.php` (16) и
  `tests/Unit/LocaleAndTimezoneOptionsTest.php` (13, включая 7 контрольных
  примеров поиска).
- L6.4b — DONE: переключатель формата времени **12/24 ч** в заголовке
  секции «World time» (`ClockWidget`). Хранение — plain-cookie
  `clock_format` (`'12'` / `'24'`, 1 год, `path=/`, `SameSite=Lax`), по
  образцу `tz`; колонка в `users` и миграция не создавались (переключение
  происходит на клиенте без Livewire-запроса). `bootstrap/app.php` —
  `clock_format` добавлен в `encryptCookies(except:)`. Серверный рендер:
  `ClockWidget::timeFormat()` (неизвестное значение → `24`), `phpTimeFormat()`
  (`H:i:s` / `h:i:s A`), формат передаётся в view как `timeFormat`. Alpine:
  корневой `x-data` виджета хранит `format`, `setFormat()` пишет cookie и
  рассылает событие `clock-format`, которое принимают все три циферблата —
  формат меняется одновременно без перезагрузки; `Intl.DateTimeFormat` для
  времени использует фиксированную локаль `en-US` и `hourCycle: h12|h23`,
  чтобы AM/PM не зависел от локали браузера. Переключатель — слот
  `afterHeader` секции (`role="group"` + `aria-pressed`), подписи
  `dashboard.clock.format_label/format_24/format_12` (`lang/{ru,en}/filament.php`);
  стили кнопок inline, потому что часть generic-утилит Tailwind отсутствует
  в CSS-бандле Filament. `localStorage` не используется (ADR-011). Тесты —
  `ClockWidgetTest` (16): 12-часовой рендер UTC и не-UTC (AM/PM), fallback
  при невалидном cookie, наличие переключателя и RU/EN подписей.
- L6.5 — DONE: интеграционная проверка. Полный Pest —
  **996 passed / 3472 assertions, 5 failed** (все 5 — предсуществующий
  класс «закрытая для гостя секция отдаёт 302 на login вместо 404»:
  `SectionAccessTest` ×2, `PermissionsMatrixPageTest` ×2,
  `EventPagesTest` ×1; поведение задано `SectionAccess` по
  frontend-spec §3.6/§9.17 и ADR-010, тесты не обновлялись в L5/L6).
  `app:translations:check` — 0 missing; `pint --test` — 443 files PASS;
  `phpstan` (Larastan) — No errors. Миграции L6: `pages` EN-колонки,
  `users.secondary_timezone` (expand-only, без backfill). Сборка
  ассетов локально не выполнялась: `node_modules` принадлежит другому
  пользователю (EACCES), `public/build` не изменялся.

**L6 — CMS и административные инструменты:**

| Подэтап | Цель | Зависимости | Миграции |
|---|---|---|---|
| L6.1 | Редакторы Header/Footer (CMS-навигация, RU/EN подписи, flex-wrap для Footer) | L5.0–L5.1 | settings или новая таблица |
| L6.2 | RU/EN CMS для Home/News/Pages: `title_en`, `body_en`, `meta_title_en`, `meta_description_en` в `pages`; fallback по полям; табы в редакторах | L5.0–L5.9 | да (`pages`) |
| L6.3 | Переключатель языка в Filament (`USER_MENU_BEFORE`); ленивый резолв navigation groups | L5.7 | нет |
| L6.4 | Dashboard часы: 3 циферблата (UTC / устройство / регион), `TimezoneResolver::browserTimezone()`, Alpine `setInterval`, inline-селект региона (optgroup, 11 регионов, 419 IANA-зон, кэш 1ч), динамическое смещение (DST) | L5.0–L5.9, L6.3 | да (`users`) |
| L6.4b | ClockWidget: переключатель формата 12/24 ч (cookie `clock_format`, Alpine-событие `clock-format`, слот `afterHeader`) | L6.4 | нет |
| L6.5 | Интеграционная проверка, приёмка, финальная документация | L3–L6.4 | нет |

**Players — исключение из L5/L6:**
Файлы `players/*.blade.php` и маршруты/логика Players не изменяются.
Общие компоненты (Header, Footer, пагинация, ActivityBell) могут
естественно затронуть страницы Players — это не считается редизайном.
Полная переработка Players — отдельный будущий проект.

**Инвентаризация (результаты аудита):**
- 49 из 66 blade-файлов содержат кириллицу (464 строки);
- 108 PHP-файлов содержат кириллицу (736 строк);
- 16 из 18 FormRequest имеют `messages()` на русском;
- 16 файлов в `app/Notifications` — русские тексты;
- `resources/views/errors/` — отсутствует (страницы ошибок не локализованы);
- `lang/` — только `ru/filament.php` и `en/filament.php`;
  `validation.php`, `auth.php`, `passwords.php`, `pagination.php` — отсутствуют;
- `fallback_locale = en` (должен быть `ru`);
- `Carbon::setLocale()` — нигде не вызывается;
- `trans_choice('игрок|игрока|игроков')` — `livewire/online-users.blade.php:4`;
- `DateFormat::localDayLabel()` — хардкод «Сегодня/Вчера»;
- `ActivityType` — русские глаголы;
- `Page::scopeInMenu` — не используется; CMS-страницы в меню не выводятся;
- `resources/views/cookie.blade.php` — не перечисляет `locale` и `tz`.

## Порядок дальнейшей работы

1. Stage 9 — Backup / restore / rollback — DONE / ACCEPTED
   (`docs/acceptance/stage-9.md`).
2. E10 — Unified MFA (ADR-009) — E10.1 + E10.2 + E10.3 + E10.4 запушены
   (`68f5eae`, `c65e1f7`, `b0c68d9`, `5274ed5`); E10.5 (admin MFA management) —
   `15f084a`; E10.5b (production MFA invariant) — `bf6a551`;
   следующий шаг — E10.6 (Filament MFA integration / removal of competing
   enforcement).
3. ADR-011 — локализация и часовые пояса — **L1 DONE / ACCEPTED**
   (2026-11-05), L1 опубликован (`e6099e9`); **L2 (`60b6d82`), L3
   (`d01b8d2`), L4 (`298f5bc`) — DONE, реализованы и закоммичены**;
   L5 (L5.0–L5.9) и L6 (L6.1–L6.5) — DONE по объёму работ L6, зафиксированы
   интеграционным коммитом вместе с `SitePageEditor`; согласованное позднее
   требование к футеру (`position: fixed; bottom: 0`) в объём L6 не входит
   и реализовано отдельно коммитом `f510157`; техническая приёмка —
   `docs/acceptance/adr-011-l5-l6.md` (**ACCEPT WITH REMARKS — pending
   owner review**); следующий шаг — проверка владельцем критериев §10
   ADR-011 (пп. 1–22); L7 — отложено.
4. Stage 10 — Tests (§9) — NOT STARTED, ожидает отдельного решения после
   локальной проверки проекта владельцем.
5. Stages 11–15 — по §30 context.md (CI, staging, deployment, production,
   update UI).
