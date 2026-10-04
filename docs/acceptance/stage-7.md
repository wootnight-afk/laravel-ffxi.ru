# Stage 7 — Dashboard: приёмочный отчёт

| Поле | Значение |
|---|---|
| Дата | 2026-10-04 |
| HEAD | 25fe067 |
| Ветка | main |
| Статус | ACCEPTED |

---

## 1. Что реализовано

- **D1:** schema и модели событий: `EventType`, `Event`,
  `EventParticipant`.
- **D2:** `EventController`, `EventService`, `EventPolicy`, Livewire
  `EventsBoard` и `EventSignup`, `EventCommentController`.
- **D3:** `Activity`, `ActivityType`, `ActivityLogger`, listeners,
  `/activity`, dashboard feed.
- **D4:** `ChatMessage`, `ChatMessagePolicy`, Livewire `ChatRoom`,
  database notifications, moderation.
- **D5:** `ActivityBell`, `OnlineUsers`, защита от утечки snapshot
  недоступного activity subject.
- **D6:** data-driven grid на основе `DashboardWidget`.
- **D7:** `SendEventStartingSoonReminderJob`,
  `EventStartingSoonNotification`, `CleanupActivities`, scheduler.
- **Listener registration:** устранено дублирование между ручной
  регистрацией и Laravel auto-discovery. Все семь activity listeners
  теперь регистрируются по одному разу.

## 2. Покрытие §9 frontend-spec

| §9 | Сценарий | Тесты | Статус |
|---|---|---|---|
| 9.10 | Чат: инкрементальная выборка, rate limit, mute, удаление | `ChatRoomTest`, `ChatModerationTest`, `ChatAcceptanceTest`, `ChatMessagePolicyTest` | ✅ |
| 9.11 | Упоминания: не себе, максимум 5, неизвестный игнорируется, подсветка, закрытый профиль без ссылки | `ChatMentionTest`, `ChatAcceptanceTest` | ✅ |
| 9.12 | События: лидер auto-join, full, `registration_close`, выход и освобождение места, reminder, фильтр типа | `EventServiceTest`, `EventLivewireTest`, `EventPagesTest`, `EventReminderTest`, `EventsAcceptanceTest` | ✅ |
| 9.16 | Bell: community-счётчик от `last_activity_seen_at`, вкладки, mark-all, personal `read_at`, бейдж `99+` | `ActivityBellTest`, `ActivityBellAcceptanceTest` | ✅ |
| 9.26 | `comment_created`, группировка, недоступный subject без snapshot и ссылки | `ActivityFeedTest`, `ActivityFeedGrouperTest`, `ActivitySubjectResolverTest`, `ActivityBellAcceptanceTest` | ✅ |

## 3. Gap-fill тесты

- `EventsAcceptanceTest` — 3 теста.
- `ChatAcceptanceTest` — 2 теста.
- `ActivityBellAcceptanceTest` — 2 теста.
- `ActivityListenersRegisteredTest` — 1 тест.
- В `ActivityFeedTest` и `ChatRoomTest` добавлены проверки количества
  activity, чтобы регрессия двойной обработки обнаруживалась явно.

## 4. Результаты проверок

- **Pest:** 381 passed (1027 assertions).
- **Pint:** PASS (230 files).
- **Larastan:** [OK] No errors.

## 5. Известные gaps

- `api_chart` / `html_board` — Stage 8, согласно ADR-008 §2.8.
- Queue worker — operational prerequisite Stage 13, не критерий приёмки
  Stage 7.
- Расхождение frontend-spec §5.9/§6.7 (queued) и ADR-008 §2.6 (sync)
  разрешено в пользу ADR-008; frontend-spec содержит устаревшую
  формулировку.
- Responsive view на реальных viewport вручную не проверялся.
- Системное дублирование activity listeners, найденное при приёмке,
  устранено: удалена ручная регистрация при сохранении Laravel
  auto-discovery.

## 6. Перенесено в Stage 8

- 12 Filament Resources.
- 4 кастомные страницы: `PermissionsMatrixPage`, `SettingsPage`,
  `BackupPage`, `UpdatePage`.
- 2FA admin и IP allowlist.
- Виджеты `api_chart` / `html_board`.

## 7. Definition of Done

- [x] §9.10–12, 16, 26 зелёные.
- [x] Pint PASS.
- [x] Larastan clean.
- [x] Ручная проверка (владелец).
- [x] Осмысленные commits.

## 8. Ссылки

- [docs/ai/context.md](../ai/context.md) §30.
- [docs/ai/frontend-spec.md](../ai/frontend-spec.md) §5.7–5.11,
  §6.7–6.8, §9.10–12, 16, 26.
- [ADR-007](../adr/ADR-007-access-control-source-of-truth.md).
- [ADR-008](../adr/ADR-008-stage-7-decisions.md).
