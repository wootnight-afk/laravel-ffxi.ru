# Project Instructions

This project follows the technical specification at `docs/ai/context.md`
(version 3.8.1, frozen).

## Spec and design documents

- `docs/ai/context.md` — technical specification (stack, security,
  deployment, backup, stages).
- `docs/designreview.md` — public site design.
- `docs/adminreview.md` — admin panel design.
- `docs/design/wireframe.html` — public mockup.
- `docs/design/admin.html` — admin mockup.

Priority on conflict: spec wins for stack/security/deploy; design docs
win for UI/UX, pages, resources, access matrix. Never invent rules
missing in either. Ask the user instead.

## Stack (locked, spec section 3)

- PHP 8.4 (`~8.4.0`)
- Laravel 13.33.0 (`^13.33`)
- Filament 5.8.4 (`^5.8`)
- Livewire 4.4.6 (`^4.4`)
- spatie/laravel-permission 8.3.0 (`^8.3`)
- intervention/image 4.3.2 (`^4.3`)
- Pest 5.2.1 + pest-plugin-laravel 5.0
- Larastan 3.12.2, Pint 1.32.1
- MySQL 8.4

## Excluded from stack

- laravel/fortify — candidate for stage 4 (MFA) only.
- bezhansahu/filament-shield — not used; policies handle authorization.
- Redis, Memcached, SQLite, Supervisor, pcntl, S3/MinIO.

## Workflow rules

1. Read `docs/ai/context.md` and relevant design docs before any task.
2. Follow the three-step work format (spec section 34):
   - Step 1 — architecture summary, wait for confirmation.
   - Step 2 — files one at a time; framework skeleton via official tools.
   - Step 3 — stop and report any contradiction or risk.
3. Never generate a file without explicit approval after Step 1.
4. Never invent packages, commands, or APIs.
5. Never research topics outside the spec.
6. Never explore projects in /home/skyw/projects/*.
7. Only use official sources (packagist.org, laravel.com, filamentphp.com,
   livewire.laravel.com, github.com raw files of official repositories).
8. When a spec §38 open question affects security, data, DB schema,
   dependencies, or public contract — stop and ask the user.

## Language

- User communication: Russian
- Code, comments, commits: English
