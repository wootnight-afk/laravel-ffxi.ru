# Stage 8 — Filament: приёмочный отчёт

| Поле | Значение |
|---|---|
| Дата | 2026-10-06 |
| HEAD | cc252b4 |
| Ветка | main |
| Статус | ACCEPTED |

---

## 1. Что реализовано

### E1 — Panel + Dashboard
- Панель `/admin`: 4 группы навигации («Основное», «Сообщество»,
  «Контент», «Система»), locale `ru`.
- `AdminDashboard`: реальные метрики, график регистраций за 30 дней,
  audit preview, shortcuts; admin и editor видят разные наборы данных.

### E2a — Suspended workflow
- Миграция `0001_01_01_000024_add_suspended_fields_to_users_table.php`
  (`suspended_at`, `suspension_reason`), case `UserStatus::Suspended`.
- `canAccessPanel()` блокирует `banned`, `suspended` и
  `deletion_requested` аккаунты независимо от роли.

### E2b — User + Role + Guest + Requests
- `UserResource` (роли, ранг, верификация, статус), `RoleResource`
  (read-only каталог), `GuestResource` (без uuid/ip_hash).
- Вкладка/фильтр «Запросы пользователей», `restore`, `SocialLinksRelationManager`.

### E7 — Settings
- `SettingsPage` (8 групп), `App\Rules\IpAllowlist`,
  `SettingsRepository::setMany` в транзакции.
- Defaults: `admin_2fa_required=false`, `admin_ip_allowlist=[]`.
- Re-auth при изменении IP allowlist.

### E3 — Permissions Matrix
- `PermissionsMatrixPage`: роли × `section.*.view` + `guest_sections`
  (`syncPermissions` + `setMany`), немедленный cache flush, admin-only.

### E4 — News + Comment
- `NewsResource` (MarkdownEditor → единый `ContentRenderer`/`HtmlSanitizer`
  pipeline, обложка, publish/reject/archive, bulk).
- `CommentResource`: plain-text комментарии, approve/reject/spam, bulk, фильтры.

### E5 — Gallery + Page + Event
- `GalleryResource` + `PhotosRelationManager` (ImageProcessor), `PageResource`
  (Markdown, menu/meta), `EventResource` (через `EventService`, cancel,
  read-only участники).

### E6 — EventType + Rank + Widget + ActivityLog
- `EventTypeResource` (admin-only gate), `RankResource`,
  `DashboardWidgetResource` (settings через KeyValue),
  `ActivityLogResource` (read-only, admin-only, фильтры).

### E8.1 — MFA (opt-in)
- Миграция `0001_01_01_000023_add_app_authentication_to_users_table.php`.
- `User` реализует `HasAppAuthentication` / `HasAppAuthenticationRecovery`.
- `AppAuthentication` в панели; `EnsureAdminMultiFactorAuthentication`
  применяет требование только при `admin_2fa_required=true` и роли `admin`.

### E8.2 — IP allowlist
- `EnsureAdminIpAllowed` в panel middleware (покрывает login и все panel routes);
  пустой список = allow-all.

### E8.3 — Audit trait
- `App\Filament\Concerns\LogsAdminActivity` + `AdminActivityLogger` +
  `AdminActivityServiceProvider` (`RecordCreated`/`RecordUpdated`,
  `DeleteAction`/`DeleteBulkAction`).
- Redaction: `email`/`password`/`phone` → `[changed]`; MFA-секреты и токены
  не пишутся; одно действие = одна запись.

### E8.4 — Re-auth
- `App\Filament\Actions\ReAuthenticateAction` (modal + `current_password`,
  только admin) для необратимых действий.

### E8.5 — Request workflow
- `restore` (status → active, контент не трогается), re-auth hard delete
  (soft delete, без каскада), фильтр «Запросы», аудит `user.restored` /
  `user.hard_deleted`.

### Дополнительно
- `fix(stage-8)`: `email_verified_at` добавлен в `#[Fillable]` `User` —
  поле формы перестало молча теряться.

---

## 2. Соответствие R1–R7

| Решение | Реализация | Тесты | Статус |
|---|---|---|---|
| **R1** — Markdown editor, единый безопасный pipeline; Comment — plain text | `MarkdownEditor` в News/Page, `ContentRenderer` + `HtmlSanitizer`; комментарии escaped | `NewsResourceTest`, `PageResourceTest`, `CommentResourceTest`, `Stage8AcceptanceTest`, `HtmlSanitizerTest` | ✅ |
| **R2** — Editor — контентный администратор | `canViewAny`/`canCreate`/`canEdit` per resource; editor ограничен группой «Контент» | `UserRoleGuestResourcesTest`, `EventTypeResourceTest`, `Stage8AcceptanceTest` | ✅ |
| **R3** — Запросы без авто-удаления контента | `restore` меняет только статус; hard delete = soft delete без каскада | `UserRequestWorkflowTest`, `ReAuthenticateActionTest`, `UserRoleGuestResourcesTest` | ✅ |
| **R4** — Аудит без secrets/PII | `LogsAdminActivity` + `AdminActivityLogger`, redaction, без дублей; смена ролей — `user.roles_changed` | `AdminActivityAuditTest`, `UserRoleAuditTest`, `ActivityLogResourceTest`, `Stage8AcceptanceTest` | ✅ |
| **R5** — MFA opt-in | `AppAuthentication` + динамический gate по `admin_2fa_required` | `MultiFactorAuthenticationTest`, `SettingsPageTest`, `Stage8AcceptanceTest` | ✅ |
| **R6** — IP allowlist opt-in | `EnsureAdminIpAllowed`, пустой список = allow-all | `AdminIpAllowlistTest`, `SettingsPageTest` | ✅ |
| **R7** — Экспорт исключён | Нет ExportAction/кнопок/кода | `Stage8AcceptanceTest` + per-resource проверки в `NewsResourceTest`, `PageResourceTest`, `GalleryResourceTest`, `EventResourceTest`, `EventTypeResourceTest`, `RankResourceTest`, `DashboardWidgetResourceTest` | ✅ |

---

## 3. Gap-fill тесты приёмки

`tests/Feature/Filament/Stage8AcceptanceTest.php` — 15 тестов (165 assertions):

1. гости → redirect на `/admin/login` для всех 24 admin-маршрутов;
2. regular user → 403 на всех 24 admin-маршрутах;
3. editor: контент доступен, «Сообщество»/«Система» → 403;
4. suspended / deletion_requested аккаунты → 403 даже с ролью admin;
5. resource-level authorization независимо от UI (`canViewAny`, `canCreate`);
6. `MarkdownEditor` используется для News и Page;
7. Markdown round-trip: raw Markdown сохраняется, `body_html` перерендерится
   и санитизируется при обновлении;
8. audit payload не содержит email/телефон/IP;
9. одно admin-действие = ровно одна audit-запись;
10. read-only list views не создают audit-шум;
11. отсутствие export action во всех list pages + отсутствие `Export` в коде;
12. sidebar ограничен 4 согласованными группами;
13. MFA self-service доступна editor и никогда не требуется;
14. регрессия Stage 1–6: публичные страницы доступны гостю;
15. регрессия Stage 7: `/activity` и `/admin` работают.

Дополнительно обновлён `AdminActivityAuditTest` — тест приведён в соответствие
с корректным поведением после fix `email_verified_at`.

### Fix R4: аудит смены ролей (отдельный коммит)

Отклонение R4, найденное при приёмке (смена роли применялась, но не
логировалась), закрыто отдельным коммитом
`fix(stage-8): audit role assignment changes (R4)`:

- `EditUser` снимает роли в `beforeSave` (до сохранения Spatie-relation) и
  сравнивает их в `afterSave`, записывая `user.roles_changed` с payload
  `old`/`new` = `{roles: [...]}`.
- Роли — relation, а не атрибут, поэтому generic-listener их не видит и
  дублей нет (подтверждено тестом «ровно одна запись»).
- `LogsAdminActivity` менять не потребовалось: `roles` не попадает в
  generic-payload.
- Bulk-смена ролей в `UserResource` отсутствует — расширение не требуется.

Тесты: `tests/Feature/Filament/UserRoleAuditTest.php` — 5 тестов (26 assertions).

---

## 4. Известные gaps

### 4.1 Прочие ограничения

| Gap | Куда перенесён |
|---|---|
| BackupPage | Stage 9 |
| UpdatePage | Stage 15 |
| `api_chart` / `html_board` виджеты | отдельный ADR |
| Экспорт | R7 — исключён осознанно |
| Уведомления пользователю при restore / hard delete | опционально, не реализовано |
| Restore очищает только статус, не `suspension_reason` | соответствует §5 контракта, но расходится с текстом ТЗ E8.5 |
| Hard delete не затирает PII и не освобождает/резервирует ник | соответствует R3, расходится с `frontend-spec.md §6.12` |
| Bulk-смена ролей в UserResource отсутствует | нет bulk-action, расширять не требуется (R4 покрывает single-action) |
| Trusted proxies / реальный IP за прокси | Stage 12 |

---

## 5. Перенесено

- Backup / restore / rollback → Stage 9.
- CI → Stage 11.
- Staging → Stage 12.
- Deployment → Stage 13.
- Production → Stage 14.
- Update mechanism → Stage 15.

---

## 6. Definition of Done §17 контракта

- [x] Реализованы все 12 согласованных Resources и две custom Pages.
- [x] Dashboard показывает реальные, проверенные метрики и разделяет
      editor/admin данные.
- [x] Sidebar имеет четыре согласованные группы, порядок и корректные
      permissions.
- [x] Policies защищают Resources, Pages, actions, bulk actions и прямые
      запросы; editor ограничен R2 без изменения `RoleAndPermissionSeeder`.
- [x] Markdown-контент проходит единый `ContentRenderer`/`HtmlSanitizer`
      pipeline; Comment остаётся plain text и выводится escaped.
- [x] Settings и Permissions Matrix используют существующие источники
      данных и корректно инвалидируют caches.
- [x] Account request workflow соответствует R3: status не запускает
      автоматическое изменение контента.
- [x] MFA self-service доступна, но не обязательна по умолчанию; allowlist
      выключен пустым списком и защищает все panel routes при включении.
- [x] Audit actions реализованы без дубликатов и без раскрытия запрещённых
      secrets/PII; `ActivityLogResource` read-only/admin-only; смена ролей
      логируется как `user.roles_changed`.
- [x] ExportAction/кнопки экспорта отсутствуют; BackupPage — Stage 9,
      UpdatePage — Stage 15, `api_chart`/`html_board` не включены.
- [x] Pest, Pint, Larastan и `git diff --check` проходят; `stage-8.md`
      отражает проверенные результаты и оставшиеся gaps.
- [ ] Ручная проверка UI владельцем — предстоит после коммита отчёта.

---

## 7. Результаты проверок

- **Pest:** 549 passed (1706 assertions).
- **Larastan:** `[OK] No errors`.
- **Pint:** PASS (321 files).
- **`git diff --check`:** exit 0.
- **`php artisan view:cache`:** OK.

---

## 8. Ссылки

- `docs/ai/STAGE-8-CONTRACT.md` §11, §12, §17.
- `docs/ai/context.md` §14, §30.
- `docs/ai/frontend-spec.md` §3.1, §7.
- `docs/acceptance/stage-7.md`.
