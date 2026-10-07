# Project Instructions

Project spec: `docs/ai/context.md` v4.0 + `docs/ai/frontend-spec.md` v1.0.

## Read first

- `docs/ai/context.md` — technical spec (stack, security, deploy, backup).
- `docs/ai/frontend-spec.md` — functional spec (roles, routes, models, UX, acceptance).
- `docs/designreview.md` — public site design.
- `docs/adminreview.md` — admin panel design.
- `docs/design/wireframe.html`, `docs/design/admin.html` — mockups.
- `docs/legal/privacy-policy-draft.md` — PD policy draft.

Priority: context.md wins for stack/security/deploy; frontend-spec.md wins
for functional logic; design docs win for UI/UX. Never invent rules
missing from either — ask the user instead.

## Stack (locked by ADR-001)

- PHP 8.4 (`~8.4.0`)
- Laravel 13.33+ (`^13.33`)
- Filament 5.8+ (`^5.8`)
- Livewire 4.4+ (`^4.4`)
- spatie/laravel-permission 8.3 (`^8.3`)
- intervention/image 4.3 (`^4.3`)
- Pest 5.2 + pest-plugin-laravel 5.0
- Larastan 3.12, Pint 1.32
- MySQL 8.4

## Excluded

- laravel/fortify — candidate for stage 4 only.
- bezhansahu/filament-shield — not used.
- Redis, Memcached, SQLite, Supervisor, pcntl, S3/MinIO.

## Roles

- admin — full access, MFA per ADR-009 (`mfa_global_enabled` + `admin_2fa_required`).
- editor — only "Content" group in admin.
- user — authorized player.
- guest — virtual + tracked via `guest_visitors` (cookie 30 days).

Matrix: spatie `section.{key}.view` + `guest_sections` JSON in settings.
Middleware: `section.access:{key}`, `registration.open`, `IdentifyGuest`.

## Workflow rules

1. Read `context.md` AND `frontend-spec.md` before any task.
2. Follow the three-step format (context.md §34):
   - Step 1: architecture summary, wait.
   - Step 2: files one at a time; framework skeleton via official tools.
   - Step 3: stop and report contradictions/risks.
3. Never create files without explicit approval after Step 1.
4. Never invent packages, commands, or APIs.
5. Never research topics outside the spec.
6. Never explore projects in `/home/skyw/projects/*`.
7. Use only official sources (packagist.org, laravel.com, filamentphp.com,
   livewire.laravel.com, github.com raw files).
8. When an open question affects security, data, DB schema, dependencies,
   or public contract — stop and ask the user.

## Language

- User communication: Russian
- Code, comments, commits: English

## Current state

Full project status: `docs/ai/PROJECT-STATUS.md`.
Autonomous work rules: `docs/ai/MASTER-TASK.md`.

Short summary:
- HEAD: `5fdf413` on `main`.
- Stages 1–9: ACCEPTED (см. docs/acceptance/).
- Stage 9 (Backup/restore/rollback): DONE / ACCEPTED — backup core +
  retention + admin BackupPage + restore (restore_requests, HMAC, CLI
  rollback) + scheduler (04:00 DB-only backup, 04:30 cleanup) + restore-test
  (isolated dev DB). Acceptance: docs/acceptance/stage-9.md.
- Tests: 678 passed.

Next step: Stage 10 — Tests (§9) coverage — NOT STARTED, ожидает отдельного
решения после локальной проверки проекта (см. PROJECT-STATUS.md).
