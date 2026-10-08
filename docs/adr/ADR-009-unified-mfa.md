# ADR-009: Unified site-wide MFA

| Поле | Значение |
|---|---|
| Статус | **Proposed / awaiting implementation** (не Accepted до приёмки E10) |
| Дата | 2026-10-07 |
| Обновлено | 2026-10-08 (E10.1 — reconciliation D1–D8; решения владельца) |
| Этап | E10 — Unified MFA (вне нумерации §30 context.md; расширение Stage 8 R5) |
| Связанные разделы | `STAGE-8-CONTRACT.md` §1 R5 (amendment), §3.4, §11; `frontend-spec.md` §3.1, §4, §6.2, §7.5, §7.6; `context.md` §13 (MFA, Session security); ADR-001 (стек), ADR-007 (доступ) |
| Причина выделения | Расширенное решение владельца: единый MFA-челлендж для всех ролей вместо admin-only Filament MFA |
| Заменяет | Противоречащие части R5 контракта Stage 8 (см. §8 R5-amendment) |

---

## 1. КОНТЕКСТ

Stage 8 реализовал MFA как **opt-in** через встроенный Filament `AppAuthentication`
(TOTP) и кастомный `EnsureAdminMultiFactorAuthentication`, применяющий требование
только при `admin_2fa_required=true` и роли `admin`.

Read-only диагностика (2026-10-07) установила фактическое состояние:

- Filament **v5.9.0**, Livewire **v4.4.7**; `AppAuthentication::make()->recoverable()`;
  panel `isRequired: true`.
- Встроенный Filament challenge — login-time и user-bound
  (`generateQrCodeDataUri()` жёстко берёт `Filament::auth()->user()`), не пригоден
  как универсальный site-wide механизм и не работает для чужого пользователя.
- `/cabinet/security` — обычный Blade/CabinetController, Livewire-компонентов нет.
- MFA permissions/policies и MFA audit events **отсутствуют**.
- Email/Notification pipeline существует; logout инвалидирует session.
- В текущей БД `admin_2fa_required = true` (runtime-состояние, запись от 2026-10-03),
  при архитектурном default `false`; настройки `mfa_global_enabled` нет.
- `pragmarx/google2fa` v9.1.0 и `pragmarx/google2fa-qrcode` v4.0.0 уже установлены
  (транзитивно через Filament); `bacon/bacon-qr-code` отсутствует, QR рендерится
  через `chillerlan/php-qrcode`.

Владелец принял расширенное решение: **единый site-wide MFA-челлендж для всех ролей**,
с сохранением Filament `AppAuthentication` только как storage/compat-слоя.

---

## 2. РЕШЕНИЕ

### 2.1. Global gate — `mfa_global_enabled`

Новая настройка `settings.mfa_global_enabled` (boolean).

| `mfa_global_enabled` | `admin_2fa_required` | Результат |
|---|---|---|
| `false` | любое | MFA enforcement **полностью выключен** |
| `true` | `false` | admin MFA **opt-in** |
| `true` | `true` | admin **обязан** иметь MFA |
| `true` | любое | user/editor — **только opt-in** |

- При `false` существующие MFA secrets/recovery codes **не удаляются**; настройки
  пользователя сохраняются и снова работают при повторном включении MFA.
- **Архитектурный default: `mfa_global_enabled = true`.** Наличие функции MFA не
  означает её глобальное отключение по умолчанию.
- Изменение runtime-БД в рамках этого ADR не производится (см. §7).

### 2.2. Admin requirement — `admin_2fa_required`

Существующая настройка сохраняется. Смысл — только в связке с `mfa_global_enabled`
(см. таблицу 2.1). `admin_2fa_required` **не влияет** на editor и user.
**Архитектурный default: `admin_2fa_required = false`.**

### 2.3. Роли и матрица enforcement

Новые роли не вводятся: `user`, `editor`, `admin`.

Enforcement применяется только к authenticated-пользователю, у которого MFA
**настроена и подтверждена**, и только при `mfa_global_enabled=true`. Если MFA не
настроена (opt-in не активирован), middleware `mfa.required` не блокирует
пользователя.

**user** — MFA opt-in. При активной MFA защищается раздел `/players` как единая
область:

- `GET /players` (`players.dashboard`);
- `GET /players/directory` (`players.directory`);
- `GET /players/{user:name}` (`players.show`).

`/cabinet/security` — escape-hatch.

**editor** — MFA opt-in. При активной MFA защищаются:

- раздел `/players` (те же три маршрута, что и у user);
- разрешённые контентные admin-поверхности (R2 `STAGE-8-CONTRACT`):
  `GET /admin/news`, `GET /admin/news/create`, `GET /admin/news/{record}/edit`,
  `GET /admin/comments`, `GET /admin/comments/{record}/edit`,
  `GET /admin/galleries`, `GET /admin/galleries/create`,
  `GET /admin/galleries/{record}/edit`,
  `GET /admin/pages`, `GET /admin/pages/create`, `GET /admin/pages/{record}/edit`,
  `GET /admin/events`, `GET /admin/events/create`, `GET /admin/events/{record}/edit`.

`GET /admin/profile` **не входит** в editor MFA-protected matrix (не является
контентной admin-поверхностью); отдельной MFA escape-hatch из него не делается,
новая функциональность для profile не создаётся. MFA UI внутри Filament profile
устраняется в E10.6 (см. §2.9). `/cabinet/security` — escape-hatch.

**admin** — при `mfa_global_enabled=true`: `admin_2fa_required=false` → opt-in;
`admin_2fa_required=true` → required. При активной MFA защищается весь `/admin/*`,
кроме escape-hatch `/admin/settings`. `/cabinet/security` — также escape-hatch.

**Global gate** — `mfa_global_enabled=false` отключает enforcement для всех ролей;
существующая MFA-конфигурация пользователей не удаляется; после повторного
включения ранее настроенная MFA снова действует по матрице.

> **Filament setup-route.** `/admin/multi-factor-authentication/set-up` — не
> отдельный route-вопрос и не escape-hatch; это существующий Filament MFA
> setup-route, который нейтрализуется в E10.6 вместе с устранением второго
> Filament MFA UX/enforcement (§2.9). Новой архитектурной роли ему не придаётся.

> Явно **вне** модели (не защищаются): `GET /activity`; вкладки `/cabinet/*`,
> кроме `/cabinet/security`; `GET /admin/login` и `POST /admin/logout` (pre-auth);
> `GET /admin/profile` для editor.

### 2.4. Escape-hatches (обязательный контракт)

Всегда доступны после обычной authentication, независимо от MFA-verification:

- **`/cabinet/security`** (user/editor/admin) — MFA setup, verification/setup
  completion, disable, recovery codes, прочие собственные MFA-операции.
- **`/admin/settings`** (admin) — существующая `SettingsPage`; её группа **Security**
  остаётся основным местом глобальных MFA-настроек.

**Отдельная страница `/admin/settings/security` не создаётся** — такого route/page
в архитектуре нет.

Escape-hatch не означает снятия security-требований: для sensitive operations
применяется повторная аутентификация согласно security-политике проекта (§6).

### 2.5. Unified challenge

Один механизм MFA для всех ролей. Отдельные challenge по ролям не создаются.

- **Routes:** `GET /mfa/challenge` → `mfa.challenge`; `POST /mfa/challenge` →
  `mfa.challenge.verify`.
- **UI:** Blade + обычный POST (Livewire для challenge не используется).
- **Recovery code:** поддерживается.
- **Успех:** session MFA verification = true; `redirect()->intended()`.
- **Провал:** generic-ошибка без раскрытия деталей; rate limiting.
- **Session state:** привязан к текущему authenticated user (§5).

### 2.6. TTL verification

MFA verification действует **до logout**. Не используются 8 часов, 30 дней,
trusted devices. Logout инвалидирует session (`session()->invalidate()`), поэтому
verification исчезает автоматически. Отдельный ручной сброс — только для
security-sensitive случаев, если он реально необходим.

### 2.7. Storage

Отдельная MFA-таблица **не вводится**. Используются существующие поля:

- `users.app_authentication_secret` (cast `encrypted`);
- `users.app_authentication_recovery_codes` (cast `encrypted:array`; значения hashed).

Encryption/casts сохраняются. Secrets/recovery codes никогда не попадают в
audit/email/logs.

### 2.8. TOTP / QR

TOTP — `pragmarx/google2fa` v9.1.0. QR-рендеринг — **существующий стек без
изменения зависимостей**:

- `pragmarx/google2fa-qrcode` v4.0.0 (транзитивно через Filament);
- `chillerlan/php-qrcode` (фактический QR-бэкенд).

`bacon/bacon-qr-code` **не добавляется**; `composer.json`/`composer.lock` в E10
не изменяются. MFA реализация использует существующие примитивы
(`AppAuthentication::generateQrCodeDataUri()`, `pragmarx/google2fa-qrcode`)
как есть. Любой будущий переход на `bacon` — отдельное решение владельца и
отдельный composer-change вне E10.

### 2.9. Filament AppAuthentication

- Встроенный Filament challenge **не используется** как параллельный site-wide
  механизм.
- `AppAuthentication` сохраняется **только как storage/compat + переиспользуемые
  примитивы** (`generateSecret`, `getSecret`, `saveSecret`, `verifyCode`,
  `verifyRecoveryCode`, `getRecoveryCodes`, `saveRecoveryCodes`,
  `generateRecoveryCodes`).
- Unified MFA заменяет: enforcement, challenge, management UI.
- Filament profile MFA UI **не должен** создавать второй самостоятельный MFA-UX.
  Основное self-service место — `/cabinet/security`.
- Существующий Filament profile MFA UI (`GET /admin/profile`, действие
  «2FA-приложение» из `AppAuthentication::getActions()`) **устраняется** как
  самостоятельный self-service интерфейс — фаза E10.6. Единственный self-service
  MFA интерфейс — `/cabinet/security`.
- **Двойного challenge быть не должно**: ни встроенный, ни unified не должны
  требовать двух последовательных проверок. Устранение конкурирующего enforcement —
  фаза E10.6.
- Filament setup-route `/admin/multi-factor-authentication/set-up`
  **нейтрализуется** в E10.6 вместе с устранением второго Filament MFA
  UX/enforcement (при отключении `isRequired` маршрут setup-required перестаёт
  применяться). Отдельной escape-hatch он не является (см. §2.3).

### 2.10. Admin reset MFA

- Admin может выполнить reset MFA другого пользователя только при наличии
  соответствующей авторизации. Роли не расширяются.
- **MVP: reset одного пользователя. Reset-all — НЕ MVP, отложен.**
- После reset: MFA configuration пользователя инвалидируется; пользователь
  получает email notification без secret/QR/recovery codes.
- Reset чужой MFA **не сбрасывает** собственную MFA-сессию администратора.

### 2.11. Authorization

Новые роли не вводятся.

- **Self-service** — каждый пользователь над своим аккаунтом
  (setup/verify/disable/regenerate). Self-service **не защищается** permission
  `mfa.manage`.
- **Административный MFA reset** — permission **`mfa.manage`**, назначается
  **только** роли `admin` (по образцу `settings.manage`/`users.manage`).
  `editor` не может выполнять MFA reset.
- `mfa.manage` существует **исключительно** для административного MFA reset;
  других применений у permission нет.
- **Reset-all не реализуется** (не MVP).

### 2.12. Audit

Минимальный утверждённый набор. MFA использует существующий audit API
`App\Services\AuditLogger::log()` (таблица `admin_audit_logs`):

- `mfa.enabled`
- `mfa.disabled`
- `mfa.admin_reset`
- `mfa.challenge_success`
- `mfa.challenge_failure`

`mfa.reset` отдельно не нужен (self-reset = `mfa.disabled`; admin-reset =
`mfa.admin_reset`). Никогда не записывать: TOTP secret, QR payload, recovery codes,
полный MFA payload. Для `challenge_failure` учитывать шум и rate limiting.

> **Расхождение Stage 8 (не исправляется в E10).** `STAGE-8-CONTRACT.md` §3.3
> описывает security-specific запись IP (`AuditLogger::log(..., includeIp: true)`,
> default `false`), тогда как текущий `app/Services/AuditLogger.php` имеет
> `recordIp` со значением по умолчанию `true`. Это отдельный технический
> долг Stage 8. E10 его скрыто не исправляет и Stage 8 implementation не меняет.

### 2.13. Email

При административном reset пользователь получает уведомление через существующий
Notification pipeline (по образцу `PasswordChangedNotification`), без secret/QR/codes.
Отдельный Mailable не создаётся.

### 2.14. Re-authentication

- `/cabinet/security` доступен без уже пройденного MFA.
- Sensitive operations не должны превращать escape-hatch в security bypass:
  как минимум disable MFA, regeneration/reissue recovery codes и другие
  destructive/security-sensitive операции требуют повторной аутентификации
  согласно существующему паттерну проекта (`ReAuthenticateAction` + `current_password`).
- Конкретная реализация re-auth выносится в E10.

### 2.15. Settings cache

MFA-настройки изменяются только через `SettingsRepository::setMany()` с
гарантированной инвалидацией `settings.all`. Прямая запись `Setting` для runtime
MFA changes не используется. Текущий риск `SettingsSeeder` без `flush` фиксируется
как отдельный тех-долг (не входит в scope ADR).

---

## 3. ПОСЛЕДСТВИЯ

### 3.1. Положительные

- Один enforcement-механизм и единый UX MFA для всех ролей.
- Сохраняется обратная совместимость storage (поля и casts не меняются).
- Escape-hatches устраняют self-lockout admin.
- Global gate позволяет выключать MFA без потери пользовательских настроек.

### 3.2. Отрицательные и принятые риски

- Требуется аккуратное выведение встроенного Filament enforcement — риск
  двойного challenge и поломки admin-логина (митигация: E10.6 + тест «no double challenge»).
- Возможен UX-разрыв между логином и post-login challenge (митигация:
  `redirect()->intended()`).
- Часть переиспользуемых Filament-примитивов — internal API (риск при апгрейде).

---

## 4. SECURITY

- Verification state привязан к текущему authenticated user; `mfa.verified = true`
  без проверки соответствия пользователю не допускается — смена authenticated user
  внутри session не должна позволять использовать чужой verification state.
- Secrets хранятся encrypted; recovery codes hashed.
- Никакие секреты не попадают в audit/email/logs.
- Rate limiting на challenge.
- Escape-hatches компенсируются re-auth для sensitive operations.

---

## 5. MIGRATION / COMPATIBILITY

- Поля `app_authentication_secret` / `app_authentication_recovery_codes` сохраняются
  (обратная совместимость с Stage 8).
- `mfa_global_enabled` добавляется как новая настройка (seeder/`setMany`), без
  миграции схемы.
- Текущее runtime-значение `admin_2fa_required = true` **не изменяется автоматически**;
  это runtime state, а не архитектурный default.

---

## 6. ROLLOUT

Пофазный план — `docs/ai/E10-MFA-CONTRACT.md` (E10.1–E10.8):

1. **E10.1** — Documentation/contract finalization + recovery point.
2. **E10.2** — Escape-hatch / `/admin/settings` access.
3. **E10.3** — MFA setup `/cabinet/security`.
4. **E10.4** — Unified challenge + `RequireMfa`.
5. **E10.5** — Admin Settings → Security (global toggle, admin requirement,
   MFA status, single-user reset, authorization).
6. **E10.6** — Filament MFA integration / removal of competing enforcement.
7. **E10.7** — Audit + email.
8. **E10.8** — Full tests + acceptance + ADR finalization.

---

## 7. TEST REQUIREMENTS

Единая acceptance matrix (детали — `E10-MFA-CONTRACT.md` §Тестовая матрица):

global OFF; global ON + admin optional; global ON + admin required; user opt-in;
editor opt-in; admin setup; `/cabinet/security` escape-hatch; `/admin/settings`
escape-hatch; `/admin/settings/security` отсутствует; `/players` protection;
editor content admin protection; full admin protection; challenge success;
challenge failure; rate limit; recovery code; recovery code consumption;
logout → verification lost; admin reset; admin reset email; no secrets in
audit/log/email; settings cache invalidation; no double challenge; current-user
binding of MFA session state.

---

## 8. R5 AMENDMENT

Исходный **R5** (`STAGE-8-CONTRACT.md` §1): Filament AppAuthentication; opt-in;
admin/editor self-service; `admin_2fa_required` reserved, default false.

**R5-amended by ADR-009:**

1. MFA становится site-wide функцией с глобальным gate `mfa_global_enabled`
   (default true).
2. Единый MFA challenge для user/editor/admin (`/mfa/challenge`); встроенный
   Filament challenge не используется как параллельный механизм.
3. `admin_2fa_required` действует только в связке `mfa_global_enabled=true` +
   `admin_2fa_required=true` → admin обязан; иначе admin/editor/user — opt-in.
4. `admin_2fa_required` не влияет на editor/user.
5. Escape-hatches обязательны: `/cabinet/security`, `/admin/settings`.
6. Setup MFA переносится в `/cabinet/security`; Filament `AppAuthentication`
   сохраняется только как storage/compat.
7. TTL verification — до logout; trusted devices не вводятся.
8. `mfa_global_enabled=false` не удаляет существующие secrets/recovery codes.
9. Audit: `mfa.enabled`, `mfa.disabled`, `mfa.admin_reset`,
   `mfa.challenge_success`, `mfa.challenge_failure`; без секретов.
10. Вводится permission `mfa.manage` (admin) для административного reset.

Пункты R5 о миграции `0001_01_01_000023`, хранении полей и recovery codes —
без изменений.

---

## 9. RISKS

- Filament v5.9.0: часть MFA-классов — internal; апгрейд может сломать
  переиспользуемые примитивы.
- `AppAuthentication` user-bound: `generateQrCodeDataUri` привязан к текущему
  panel-user; admin-reset чужого secret = обнуление, без показа QR чужому.
- Middleware order: `RequireMfa` должен идти после `auth`/`AuthenticateSession`/
  `EnsureAdminIpAllowed` и корректно сосуществовать с `section.access`.
- Двойной challenge — главный риск (закрывается E10.6 + тест).
- Session lifecycle: сброс verification для admin-reset чужой MFA не должен
  затрагивать свою сессию.
- Authorization: новый permission `mfa.manage`; при отсутствии — гейт по роли admin.
- Encrypted secrets/recovery codes: работа с чужим пользователем только через
  модель-инстанс, не через panel-bound API.
- Database settings: `mfa_global_enabled` отсутствует; stale-cache
  (`SettingsSeeder` без `flush`) — тех-долг.
- Legacy `admin_2fa_required=true` в текущей БД: runtime-состояние, снимается через
  escape-hatch + Settings, не автоматически.
- Email pipeline: smtp/mailpit; часть notifications queued.
- QR-стек: используется существующий `pragmarx/google2fa-qrcode` v4.0.0 +
  `chillerlan/php-qrcode`; `bacon/bacon-qr-code` не добавляется, composer в E10
  не изменяется.
