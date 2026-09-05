---
title: '1.1 Root Laravel scaffold and design tokens'
type: 'feature'
created: '2026-09-05'
status: 'done'
baseline_commit: 'NO_VCS'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/planning-artifacts/ux-designs/ux-guidely-app-2026-08-20/DESIGN.md'
  - '{project-root}/_bmad-output/planning-artifacts/architecture/architecture-guidely-app-2026-08-20/ARCHITECTURE-SPINE.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Architecture was changed (2026-09-05) from `apps/api`+`apps/web` to classic root Laravel + Vue SPA. The prior scaffold is archived; the repo needs a fresh root Laravel app with a Vue SPA (no Inertia) and DESIGN.md tokens.

**Approach:** Scaffold Laravel 13 at the repository root, add Vue 3.5 + Vite 8 + Tailwind 4 under `resources/js` as a client-side SPA (Vue Router) talking to `/api/v1/*`, expose JSON health, map design tokens, document runbook, and ship automated tests. Do not install or use Inertia.

## Boundaries & Constraints

**Always:**
- Classic root Laravel layout (`composer.json`, `app/`, `routes/` at project root).
- Laravel 13 / PHP 8.3+; Vue 3.5.x; Vite 8.x; Tailwind 4.x; UK English.
- `GET /api/v1/health` returns JSON (`status`, `service`, `version`).
- Vue SPA under `resources/js` with Vue Router; Blade only as SPA shell loading Vite.
- DESIGN.md colour, typography, spacing, radius as CSS/Tailwind variables; light mode only.
- Brand wordmark: SVG or curated script — never Comic Sans in production.
- No parent/LMS/clinician/EduConnect wireframe product routes.
- All I/O matrix scenarios covered by automated tests that pass before completion.

**Ask First:**
- Initialising git (repo currently has no `.git`).
- Changing Architecture stack majors.

**Never:**
- Inertia (or Inertia-driven product pages).
- Auth, tenancy models, Role shell, Capacitor packaging, Domain business logic.
- Dark mode, purple glow, neon, or wireframe family-care screens.
- Marking done without green health + token + IA + no-Inertia tests.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Health happy path | `GET /api/v1/health` | `200` JSON `{status, service, version}` for GuidelyEdu API | N/A |
| Health unknown route | `GET /api/v1/does-not-exist` (plain GET) | Non-200 JSON; no HTML Blade product shell | Framework JSON via `api/*` |
| Token CSS / theme | Token stylesheet | `@theme` has canvas, primary `#5B63E6`, secondary `#10B981`, page `24px`, radius ~`12px`, topbar/sidebar sizes | Fail if missing/wrong |
| No Inertia | `composer.json` / `package.json` | No `inertiajs` / `@inertiajs` dependencies | Fail if present |
| No forbidden IA | Vue router route table | No parent portal, LMS, clinician EMR, EduConnect routes | Fail if present |
| README runbook | Root README | Documents `artisan serve`, Vite/npm, and `queue:work` | Fail if missing |

</frozen-after-approval>

## Code Map

- Repo root currently: `_bmad/`, `_bmad-output/`, `_archive/apps-monorepo-v1-superseded/`, `.agents/`, `docs/` — no live Laravel app.
- Architecture Structural Seed (updated 2026-09-05): root Laravel + `resources/js` Vue SPA; no Inertia.
- Create Laravel via `composer create-project` into a temp dir then merge into root (preserve `_bmad*`, `docs`, `_archive`, `.agents`).
- Vue: install vue, vue-router, tailwind 4, vitest; SPA entry + shell Blade; tokens CSS.
- Health route + Feature tests; scaffold Vitest suite.

## Tasks & Acceptance

**Execution:**
- [x] Root Laravel 13 scaffold -- Merge into project root without wiping planning folders
- [x] `GET /api/v1/health` + JSON `api/*` errors -- AD-1 surface
- [x] `resources/js` Vue 3.5 SPA (Vue Router, no Inertia) + Tailwind 4 tokens -- UX-DR1–3
- [x] PHPUnit health tests + Vitest token/IA/no-Inertia tests -- matrix coverage
- [x] README + `.gitignore` -- operator runbook; ignore vendor/node_modules

**Acceptance Criteria:**
- Given the updated Architecture seed, when scaffolded, then root Laravel 13 serves JSON `/api/v1/health` and `resources/js` is a Vue 3.5 + Vite 8 + Tailwind 4 SPA without Inertia.
- Given DESIGN.md, when styles load, then colour/typography/spacing/radius tokens exist (light only).
- Given the Vue router, when inspected, then no wireframe parent/LMS/clinician routes exist.
- Given README, when followed, then API, Vite frontend, and queue worker can be run locally.
- Given verification commands, when run, then all tests pass.

## Design Notes

Local SQLite OK; README names PostgreSQL for non-local. Prefer `@theme` single hex source for tokens. Health: `{"status":"ok","service":"guidely-api","version":"v1"}`. Keep `_archive/apps-monorepo-v1-superseded` untouched as historical reference.

## Verification

**Commands:**
- `php artisan test --filter=Health` -- expected: health tests pass
- `npm test` -- expected: token + IA + no-Inertia tests pass
- `php artisan route:list --path=api/v1/health` -- expected: GET registered
- `npm run build` -- expected: Vite production build succeeds

**Manual checks (if no CLI):**
- `composer.json` / `package.json` have no Inertia packages; README covers serve / npm / queue.

## Suggested Review Order

**API**

- Versioned health JSON at root Laravel
  [`api.php:5`](../../routes/api.php#L5)

- Force JSON for `api/*` errors
  [`app.php:19`](../../bootstrap/app.php#L19)

**Vue SPA (no Inertia)**

- SPA shell Blade + catch-all excluding `api`
  [`web.php:5`](../../routes/web.php#L5)

- Vue Router home-only routes
  [`index.js`](../../resources/js/router/index.js)

**Tokens & tests**

- DESIGN.md `@theme` tokens
  [`app.css`](../../resources/css/app.css)

- Health + plain-GET unknown route
  [`HealthTest.php:9`](../../tests/Feature/HealthTest.php#L9)

- No-Inertia + IA guards
  [`scaffold.test.js:8`](../../resources/js/tests/scaffold.test.js#L8)
