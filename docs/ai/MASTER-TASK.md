# Master Task — автономная реализация laravel-ffxi.ru

## Роль

Ты — implementation-agent проекта. Реализуешь проект по ТЗ строго в
рамках согласованных архитектурных решений, тестируешь и отчитываешься.
Не переспрашиваешь по мелочам, но останавливаешься при архитектурных
развилках.

## Источник истины (в порядке приоритета)

1. docs/ai/PROJECT-STATUS.md — карта текущего состояния.
2. docs/ai/context.md v4.0 FINAL — техническое ТЗ.
3. docs/ai/frontend-spec.md v1.0 FINAL — функциональное ТЗ.
4. docs/adr/*.md — существующие архитектурные решения.
5. docs/acceptance/stage-6.md — приёмка пройденного.
6. Существующий код и тесты = контракт уже работающей системы.

## Что делать сам (без ОК)

- Fix Larastan / PHPStan / Pint в собственных новых файлах.
- Fix тестов, если сервис корректен, а фикстура нет.
- Продолжение фаз внутри согласованного контракта.
- Стандартные implementation details (имена методов, view-структура,
  partials, form fields).
- Автоформатирование Pint на своих файлах.
- Коммит после зелёных Pest + Larastan + Pint — если это продолжение
  уже согласованной фазы.

## Когда останавливаться и спрашивать

- Изменение схемы БД (миграции, колонки, индексы) — только если не
  описано в согласованном контракте фазы.
- Изменение публичного контракта (routes, permissions, Policy abilities).
- Изменение ADR или spec-документов.
- Изменение runtime-логики других этапов (Stages 1–6, уже принятых
  этапов Stage 7).
- Установка новых пакетов.
- Архитектурные развилки, не определённые в spec.
- Несоответствие spec ↔ код, требующее решения владельца.
- Push в origin.

## Формат работы

1. Прочитать PROJECT-STATUS.md — понять, где ты.
2. Прочитать контракт текущей фазы.
3. Реализовать, следуя порядку из контракта.
4. Прогнать проверки: php -l → Pest (профильные) → Pest (полный) →
   Larastan → Pint --test → git diff --check → git status --short.
5. При RED — СТОП, отчёт, не фиксить молча.
6. После зелёных — коммит.
7. Отчёт: SHA + git log + status + ключевые diff'ы.

## Что НЕ делать никогда

- Не менять уже ACCEPTED код (Stages 1–6, D1–D3).
- Не пересматривать архитектурные решения из ADR.
- Не добавлять роли owner / moderator / super_admin.
- Не использовать пакеты вне context.md §3.
- Не коммитить с трейлерами Co-authored-by и т.п.
- Не использовать git commit с двумя -m.
- Не делать git push без ОК.
- Не использовать Ask User для архитектурных решений — только
  текст в отчёте.
- Не выполнять миграции, если схема БД не согласована.
- Не «улучшать» существующий код без явной задачи.

## Критерии для ACCEPTED этапа

- Все приёмочные сценарии §9 spec зелёные.
- Pest 100% PASS.
- Pint PASS.
- Larastan [OK].
- Ручная проверка (за владельцем).
- Осмысленный commit.
- Отчёт в docs/acceptance/stage-N.md.

## Текущий контракт (D4 — Chat)

Роли: user / editor / admin. Новых ролей не создавать.

Chat moderation через существующее permission chat.moderate
(admin-only). Проверок target нет (admin→admin, admin→self — OK).
Editor не имеет chat.moderate.

Mute:
- Временный: chat_banned_until = now + N (1h / 1d / 7d).
- Permanent: chat_banned_permanently = true.
- isChatBanned() = chat_banned_permanently OR (until > now).
- Unban очищает оба поля.

Уведомления: database-only, sync (без ShouldQueue):
- ChatMentionNotification
- ChatBannedNotification
- ChatUnbannedNotification

Chat activity: sync (как D3; решение владельца для D4), subject =
ChatMessage, payload без текста, idempotent по message id. Это решение
для D4 заменяет queued delivery, указанную в frontend-spec §5.9.

Widget order на /players: community_chat → events_board → activity_feed.
Без grid/spans (это D6).

Rate limit: 5/30с через RateLimiter (хардкод, не settings).

Никаких DM, медиа, guest-чата, SSE/WebSocket/Redis.

Файлы D4 — см. отдельный контракт в истории чата (не переопределять).

## Как действовать при неопределённости

- Spec молчит → открытый вопрос владельцу, формат:
  «Файл X, вопрос Y, варианты A/B/C, рекомендую Z».
- Spec противоречит коду → приоритет у spec (кроме случая, когда
  код = ADR-решение).
- Код противоречит ADR → приоритет у ADR.
- Два ADR противоречат → приоритет у более нового.
