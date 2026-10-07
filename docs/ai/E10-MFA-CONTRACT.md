# E10 — Unified MFA. Контракт реализации

| Поле | Значение |
|---|---|
| Статус | Draft (awaiting implementation) |
| Дата | 2026-10-07 |
| Основание | `docs/adr/ADR-009-unified-mfa.md` (Proposed); amendment R5 |
| Связанные | `STAGE-8-CONTRACT.md` §1 R5 (amended), §3.4, §11; `frontend-spec.md` §3.1, §4, §6.2, §7.5, §7.6; `context.md` §13 |

Контракт фиксирует архитектуру unified MFA (ADR-009) и пофазный план E10.
Реализация не начата. Порядок фаз обязателен: E10.1 → E10.2 → E10.3 → E10.4 →
E10.5 → E10.6 → E10.7 → E10.8.

---

## 1. Термины

- **enforcement** — серверная проверка, требующая пройденного MFA.
- **challenge** — единый экран `/mfa/challenge` (GET/POST).
- **verification** — session-состояние «MFA пройден для текущего пользователя».
- **escape-hatch** — маршрут, доступный без пройденного MFA.

---

## 2. Целевая модель (сводка ADR-009)

1. Global gate `mfa_global_enabled` (default **true**).
2. `mfa_global_enabled=false` → enforcement выключен для всех; secrets/codes
   сохраняются.
3. `mfa_global_enabled=true` + `admin_2fa_required=true` → admin обязан MFA.
4. `mfa_global_enabled=true` + `admin_2fa_required=false` → admin opt-in.
5. user/editor — всегда opt-in; `admin_2fa_required` на них не влияет.
6. Единый challenge `/mfa/challenge` (`mfa.challenge`, `mfa.challenge.verify`),
   Blade + POST.
7. Verification — до logout; session привязана к текущему user.
8. Escape-hatches: `/cabinet/security`, `/admin/settings`.
9. Отдельной `/admin/settings/security` нет.
10. Storage: `users.app_authentication_secret`,
    `users.app_authentication_recovery_codes` (encrypted / encrypted:array, hashed).
11. Filament `AppAuthentication` — storage/compat; встроенный challenge не
    используется; двойного challenge нет.
12. Admin reset — single-user (permission `mfa.manage`); reset-all не MVP.
13. Audit: `mfa.enabled`, `mfa.disabled`, `mfa.admin_reset`,
    `mfa.challenge_success`, `mfa.challenge_failure`.
14. Email при admin reset — без секретов.

---

## 3. Пофазный план

### E10.1 — Documentation / contract finalization + recovery point (S)

- **Цель:** зафиксировать контракт и точку отката до изменений кода.
- **Файлы:** `docs/adr/ADR-009-unified-mfa.md`, `docs/ai/E10-MFA-CONTRACT.md`,
  amendment в `docs/ai/STAGE-8-CONTRACT.md`, синхронизация `frontend-spec.md` /
  `context.md` / acceptance-документов.
- **Зависимости:** нет.
- **Изменения:** только документация; recovery point (commit-метка) перед E10.2.
- **Tests:** нет (docs).
- **Acceptance criteria:** ADR-009 и контракт согласованы; R5-amendment внесён;
  recovery point зафиксирован.
- **Rollback / recovery point:** revert docs-коммита; код не затрагивается.
- **DONE:** документы в рабочем дереве, противоречия reconciliation закрыты.

### E10.2 — Escape-hatch / `/admin/settings` access (S)

- **Цель:** устранить self-lockout — `/admin/settings` и `/cabinet/security`
  доступны независимо от MFA.
- **Файлы:** `app/Http/Middleware/EnsureAdminMultiFactorAuthentication.php`,
  `app/Providers/Filament/AdminPanelProvider.php`.
- **Зависимости:** E10.1.
- **Изменения:** исключить `admin/settings*` из MFA-middleware; подтвердить, что
  `/cabinet/security` вне panel-MFA.
- **Tests:** Feature — admin без MFA при `admin_2fa_required=true` открывает
  `/admin/settings` (200); прочие admin-роуты → redirect на setup; editor не затронут.
- **Acceptance criteria:** владелец может зайти в Settings и управлять флагами.
- **Rollback / recovery point:** revert правок middleware/panel.
- **DONE:** escape-hatch работает; остальные routes под MFA как раньше.

### E10.3 — MFA setup `/cabinet/security` (M/L)

- **Цель:** self-service MFA (setup/verify/disable/recovery) для любой роли.
- **Файлы:** `resources/views/cabinet/tabs/security.blade.php`,
  `app/Http/Controllers/Cabinet/MfaController.php` (новый), `routes/web.php`,
  сервис-обёртка над AppAuthentication, lang-файлы; при необходимости `User`.
- **Зависимости:** E10.1; storage/QR решения ADR-009 §2.7–2.8.
- **Изменения:** генерация secret + QR, подтверждение TOTP, показ/регенерация
  recovery codes, disable; Blade + POST; только свой аккаунт; re-auth для
  disable/regenerate.
- **Tests:** setup flow, verify, disable, recovery regenerate; секреты не в
  audit/email; чужой аккаунт недоступен.
- **Acceptance criteria:** все роли включают/выключают MFA и получают recovery codes.
- **Rollback / recovery point:** revert фазы (routes/view/controller).
- **DONE:** setup MFA работает из `/cabinet/security`.

### E10.4 — Unified challenge + `RequireMfa` (L)

- **Цель:** единый enforcement и challenge.
- **Файлы:** `app/Http/Middleware/RequireMfa.php` (alias `mfa.required`),
  `app/Http/Controllers/MfaChallengeController.php`,
  `resources/views/mfa/challenge.blade.php`, `routes/web.php`, `bootstrap/app.php`.
- **Зависимости:** E10.3.
- **Изменения:** session-ключи verification (привязка к user), challenge GET/POST,
  rate limit, recovery path, `redirect()->intended()`, применение к `/players` и
  защищённым admin-маршрутам по матрице §2.3; escape-hatches исключены.
- **Tests:** middleware-матрица (см. §4), challenge success/failure, recovery,
  logout-инвалидация, current-user binding.
- **Acceptance criteria:** enforcement по матрице ролей; escape-hatches доступны.
- **Rollback / recovery point:** revert middleware/routes.
- **DONE:** единый challenge применяется к user/editor/admin.

### E10.5 — Admin Settings → Security (M/L)

- **Цель:** управление MFA из `SettingsPage` → Security.
- **Файлы:** `app/Filament/Pages/SettingsPage.php` (+ MFA-секция), таблица/Resource
  MFA-status, permission `mfa.manage` в `RoleAndPermissionSeeder`, гейты.
- **Зависимости:** E10.4.
- **Изменения:** global toggle `mfa_global_enabled`, `admin_2fa_required`,
  список MFA-status, per-user reset (`requiresConfirmation` + `ReAuthenticateAction`).
- **Tests:** авторизация reset (admin да, editor нет, self да), global toggle влияет
  на enforcement, настройки сохраняются через `setMany`.
- **Acceptance criteria:** admin управляет MFA; editor не может.
- **Rollback / recovery point:** revert фазы.
- **DONE:** управление работает, permission enforced.

### E10.6 — Filament MFA integration / removal of competing enforcement (S/M)

- **Цель:** исключить двойной challenge; зафиксировать роль AppAuthentication.
- **Файлы:** `app/Providers/Filament/AdminPanelProvider.php` (isRequired → false,
  вывод middleware), при необходимости кастомный Filament Login page.
- **Зависимости:** E10.4.
- **Изменения:** отключить встроенный login-time challenge; AppAuthentication —
  только storage/compat; `/admin/multi-factor-authentication/*` не используется.
- **Tests:** «no double challenge» — MFA спрашивается ровно один раз.
- **Acceptance criteria:** один MFA-challenge на входе.
- **Rollback / recovery point:** revert панели/логина.
- **DONE:** двойной challenge отсутствует.

### E10.7 — Audit + email (S/M)

- **Цель:** аудит и уведомления.
- **Файлы:** вызовы `AuditLogger` в MFA-контроллерах,
  `app/Notifications/MfaResetByAdminNotification.php` (новый).
- **Зависимости:** E10.3–E10.5.
- **Изменения:** 5 событий (ADR-009 §2.12); notification при admin reset.
- **Tests:** события пишутся без секретов; email отправляется без секретов.
- **Acceptance criteria:** аудит/email по ADR-009 §2.12–2.13.
- **Rollback / recovery point:** revert фазы.
- **DONE:** события и уведомление работают.

### E10.8 — Full tests + acceptance + ADR finalization (M)

- **Цель:** полное покрытие и приёмка.
- **Файлы:** `tests/Feature/Mfa/*`, `docs/acceptance/stage-10.md` (или `E10.md`),
  финализация ADR-009 (Status → Accepted).
- **Зависимости:** все предыдущие.
- **Изменения:** тест-матрица §4; отчёт приёмки.
- **Tests:** полный Pest / Larastan / Pint.
- **Acceptance criteria:** DoD E10 выполнен; отчёт фиксирует gaps.
- **Rollback / recovery point:** n/a (docs/tests).
- **DONE:** приёмка подписана владельцем.

---

## 4. Тестовая матрица (единая acceptance matrix)

| # | Сценарий | Ожидание |
|---|---|---|
| T1 | global OFF (`mfa_global_enabled=false`), user/editor/admin, MFA настроена и не настроена | enforcement отключён для всех; secrets/codes сохраняются |
| T2 | global ON + admin optional (`admin_2fa_required=false`) | admin без MFA не блокируется |
| T3 | global ON + admin required (`admin_2fa_required=true`), admin без MFA | `/admin/settings` — 200; `/cabinet/security` — 200; прочие защищённые admin-роуты → setup/challenge |
| T4 | editor, `admin_2fa_required=true` | MFA opt-in; требование не влияет |
| T5 | user | MFA opt-in |
| T6 | admin setup MFA | setup доступен из `/cabinet/security` |
| T7 | `/cabinet/security` escape-hatch | доступен при незавершённой MFA |
| T8 | `/admin/settings` escape-hatch | доступен admin при незавершённой MFA |
| T9 | `/admin/settings/security` | маршрут отсутствует (404) |
| T10 | `/players` protection | при настроенном MFA и незавершённой verification → challenge |
| T11 | editor content admin protection | `/admin/news|comments|gallery|pages|events` защищены при настроенном MFA |
| T12 | full admin protection | весь `/admin/*` кроме `/admin/settings` защищён |
| T13 | challenge success | verification = true; `redirect()->intended()` |
| T14 | challenge failure | generic-ошибка; verification не устанавливается |
| T15 | rate limit | превышение попыток блокируется |
| T16 | recovery code | принимается, если валиден |
| T17 | recovery code consumption | использованный код расходуется (удаляется) |
| T18 | logout → MFA verification lost | после logout verification отсутствует |
| T19 | admin reset | конфигурация пользователя инвалидируется |
| T20 | admin reset email | пользователь получает email |
| T21 | no secrets in audit/log/email | secret/QR/recovery codes отсутствуют |
| T22 | settings cache invalidation | изменение через `setMany` немедленно влияет на enforcement |
| T23 | no double challenge | встроенный Filament и unified не требуют двух challenge |
| T24 | current-user binding | смена authenticated user не позволяет использовать чужой verification |

---

## 5. Риски и зависимости

См. `ADR-009-unified-mfa.md` §9. Ключевые: двойной challenge (E10.6),
middleware order, `mfa.manage`, stale settings cache, QR-стек (composer вне scope),
legacy runtime `admin_2fa_required=true`.

---

## 6. Открытые вопросы (владельцу)

Решения владельца зафиксированы; блокирующих ADR вопросов нет:

1. QR-стек — решение владельца `bacon/bacon-qr-code`; фактическая необходимость
   отдельного composer-change выносится в scope E10 (не блокирует ADR).
2. Filament profile MFA UI — второй самостоятельный MFA-UX не создаётся;
   основное self-service место `/cabinet/security` (решено).
3. Форма challenge — Blade + POST (решено).
