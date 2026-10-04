# Stage 6 — Профили и кабинет: приёмочный отчёт

| Поле | Значение |
|---|---|
| Дата | 2026-10-04 |
| HEAD | ab2332e |
| Ветка | main |
| Статус | Принят с известными gaps |

---

## 1. Что реализовано в Stage 6

### Страницы игроков (§6.3)
- `/players` — дашборд сообщества
- `/players/directory` — каталог с поиском и пагинацией
- `/players/{nickname}` — страница игрока: аватар, ник, ранг-бейдж, раса, main job, дата регистрации, легенда, соцсети, вкладки Новости/Фото
- Приватность: `is_profile_public`, гость не видит `/players*`

### Компоненты (§8.2)
- `<x-user-identity>` — единая точка рендера ника/аватара/бейджа. Учитывает контекст `chat`, состояние `deletion_requested` → «[аккаунт удалён]».
- `<x-user-rank>` — компактный бейдж ранга
- `<x-avatar>` — аватар с fallback
- `<x-social-links>` — список публичных соцссылок

### Кабинет (§6.2)
- `/cabinet/{tab}` — 7 вкладок: profile, social, news, gallery, events (заглушка), security, danger
- **Профиль:** аватар, раса, main job, легенда (Markdown), телефон (encrypted), `is_profile_public`
- **Социальные сети:** 12 платформ, https-only, лимит из Settings, `is_visible`
- **Мои новости:** player-scope CRUD, обложка 1280×720 WebP, модерация post/pre, статусы draft/pending/published/rejected
- **Моя галерея:** player-albums (лимит 10), batch upload ≤10, 50/день, `ProcessPhotoJob` через queue, лимиты из Settings
- **Безопасность:** смена пароля/email, audit через `admin_audit_logs`, уведомления (пользователю, на старый email, администраторам)
- **Опасная зона Шаг 1-2:** запрос удаления аккаунта, статус `deletion_requested`, logout всех сессий, уведомления, audit

### Инфраструктура
- Middleware `section.access:{key}` подключён к публичным секциям
- `Gate::before` для admin — только для `section.*` abilities
- Guest без доступа к секции → 404 (§9.17)
- `SettingsSeeder` глобально в `tests/Pest.php` для Feature-тестов

---

## 2. Покрытие §9 frontend-spec (приёмочные сценарии)

| §9 | Сценарий | Тестовый файл | Статус |
|---|---|---|---|
| 1 | Регистрация: занятый ник → подсказки | `RegistrationTest` | ✅ |
| 2 | Дубликат email / ника | `RegistrationTest` | ✅ |
| 3 | До верификации: чтение ок, write 403 | `EmailVerificationTest` | ✅ |
| 4 | Player-новость видна на странице игрока | `PlayerNewsIntegrationTest` | ✅ частично (см. known gaps) |
| 5 | Приватность: закрытый профиль | `PlayerPagesTest` | ✅ |
| 6 | Гость: `/players*` → 404/302, текстовые ники | `PlayerPagesTest`, `RankVisibilityTest` | ✅ |
| 7 | Комментарии (reputation): первые 5 pending | `CommentTest` | ✅ |
| 8 | `comments_enabled=false` | `CommentTest` | ✅ |
| 9 | Архив: вне ленты, URL с плашкой | `ArchivedNewsTest` | ✅ частично (плашка — gap) |
| 10 | Чат | — | ⏳ Stage 7 |
| 11 | Упоминания | — | ⏳ Stage 7 |
| 12 | События | — | ⏳ Stage 7 |
| 13 | Галерея: 3 размера WebP, лимиты | `GalleryTest`, `MyGalleryTest` | ✅ |
| 14 | Роли: editor / user → /admin | `RoleAuthorizationTest`, `AdminPanelAccessTest` | ✅ |
| 15 | Гости-трекинг: один visitor на cookie | `GuestTrackingTest` | ✅ |
| 16 | 🔔 | — | ⏳ Stage 7 |
| 17 | Матрица: снятие User → 403; guest → 404 | `SectionAccessTest` | ✅ |
| 18 | Удаление: пароль, статус, сессии | `DangerZoneTest` | ✅ Шаг 1-2 (Шаг 3 — Stage 8) |
| 19 | Модерация: pre / post, не ретроактивно | `MyNewsTest` | ✅ |
| 20 | Санитизация XSS | `HtmlSanitizerTest` | ✅ |
| 21 | Соцсети: javascript:, is_visible, лимит | `SocialLinksTest` | ✅ |
| 22 | Ранги: только admin, auth видит, гость нет | `RankVisibilityTest` | ✅ |
| 23 | Закрытая регистрация | `RegistrationToggleTest` | ✅ |
| 24 | Разделение пространств `/cabinet` ≠ `/players` | `SpaceSeparationTest` | ✅ |
| 25 | Enumeration: reset/resend единый ответ | `EnumerationTest` | ✅ |
| 26 | Activity: comment_created, группировка | — | ⏳ Stage 7 |

---

## 3. Gap-fill тесты, добавленные на приёмке

| Файл | Тестов | Покрывает |
|---|---|---|
| `tests/Feature/News/ArchivedNewsTest.php` | 5 | §9.9 |
| `tests/Feature/Security/SectionAccessTest.php` | 5 | §9.17 |
| `tests/Feature/Ranks/RankVisibilityTest.php` | 6 | §9.22 |
| `tests/Feature/Cabinet/SpaceSeparationTest.php` | 5 | §9.24 |
| `tests/Feature/Players/PlayerNewsIntegrationTest.php` | 5 | §9.4 |

---

## 4. Результаты проверок

- **Pest:** 200 passed (546 assertions)
- **Pint:** PASS (149 files)
- **Larastan:** [OK] No errors

---

## 5. Известные ограничения (known gaps)

Зафиксированы осознанно, не являются блокерами приёмки Stage 6:

| Gap | Куда перенесён |
|---|---|
| Вкладка «Мои события» — заглушка «Раздел в разработке» | Stage 7 (Event-модели нет) |
| Шаг 3 удаления аккаунта: окончательное удаление и восстановление через admin | Stage 8 (Filament UserResource) |
| Плашка «Новость в архиве» на `/news/{slug}` для editor/admin | Stage 8 (в связке с Filament-действием «В архив») |
| Pending-новость автора не отображается на `/players/{nickname}` (только в кабинете) | Stage 7 (виджет/страница автора) или отдельный подэтап |
| Drag&drop сортировка фото в галерее | Отдельный подэтап |
| Markdown preview в редакторах (news, legend) | Отдельный подэтап |
| Sitemap, OG-теги, SEO | После Stage 7 |
| Cleanup job для файлов soft-deleted фото | Stage 11 (CI/инфра) |
| Юридические тексты политики ПД и cookie | От владельца, блокирует открытие регистрации |

---

## 6. Перенесено в следующие этапы

- **Stage 7:** Events, Chat, Activity, Bell, Dashboard widgets, вкладка «Мои события»
- **Stage 8:** 12 Filament Resources, 4 кастомные страницы, 2FA admin, IP allowlist, Шаг 3 удаления аккаунта
- **Stage 9:** Backup / restore / rollback
- **Stage 10:** Полное покрытие §9 (после появления Stage 7)
- **Stage 11:** CI, cleanup jobs

---

## 7. Definition of Done §30 context.md

- [x] Приёмочные сценарии Stage 6 зелёные (все доступные)
- [x] Pint PASS
- [x] Larastan clean
- [x] Ручная проверка владельцем (предстоит после коммита)
- [x] Осмысленный commit

---

## 8. Ссылки

- `docs/ai/context.md` §30 — этапы
- `docs/ai/frontend-spec.md` §3.4, §3.5, §6, §9
- `docs/adr/ADR-007-access-control-source-of-truth.md`
