# Project Instructions

This project follows the technical specification at `docs/ai/context.md`.

## Stack (locked by spec, section 3)

- PHP 8.4
- Laravel 13.x (current supported stable major, verified via Packagist)
- Filament 5.x — preferred; Filament 4.x compared in ADR-001 per section 3
- Livewire 4.x (explicit require)
- MySQL 8.4
- Pest 5.x for testing
- Laravel Pint + Larastan + composer audit for CI
- Docker for local dev, Timeweb shared hosting for production

## Excluded from stack (candidates only)

- laravel/fortify — candidate for stage 4 (MFA), NOT part of the stack.
  Add only via separate PR with ADR justification if Filament 5 does
  not provide MFA out of the box.

## Workflow rules

1. **Always read `docs/ai/context.md` before starting any task.**
2. Follow the three-step work format from section 34 of the spec:
   - Step 1 — architecture summary, then wait for confirmation.
   - Step 2 — after confirmation, generate files one at a time,
     complete, no placeholders.
   - Step 3 — if any contradiction, incompatibility, or missing
     capability is found, STOP and report before continuing.
3. Generate complete files, no placeholders.
4. Do not create files without explicit approval after Step 1.
5. Report contradictions or incompatibilities immediately (Step 3).
6. Do not invent packages, commands, or APIs.
7. Check actual package existence and compatibility before proposing.
8. Do not explore other projects in /home/skyw/projects/*.
9. Do not research topics not present in docs/ai/context.md.
10. Only use official sources: packagist.org, repo.packagist.org,
    official docs (laravel.com, filamentphp.com, livewire.laravel.com),
    github.com raw files of official repositories.

## Language

- User communication: Russian
- Code, comments, commits: English
