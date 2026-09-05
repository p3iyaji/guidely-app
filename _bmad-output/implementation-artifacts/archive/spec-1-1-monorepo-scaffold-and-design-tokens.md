---
title: '1.1 Monorepo scaffold and design tokens'
type: 'feature'
created: '2026-09-05'
status: 'done'
baseline_commit: 'NO_VCS'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/planning-artifacts/ux-designs/ux-guidely-app-2026-08-20/DESIGN.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The repo is planning-only. There is no runnable API or SPA, so later Epic 1 stories have nowhere to land.

**Approach:** Create `apps/api` (Laravel 13 JSON API + workers) and `apps/web` (Vue 3.5 + Vite 8 + Tailwind 4), expose versioned JSON health, map DESIGN.md tokens as CSS/Tailwind variables (light only), document local runs in README, and ship automated tests for health + tokens + forbidden-IA absence.

## Boundaries & Constraints

**Always:**
- Layout `apps/api` + `apps/web` (not single-app Laravel product UI).
- Laravel 13 / PHP 8.3+; Vue 3.5.x; Vite 8.x; Tailwind 4.x; UK English.
- `GET /api/v1/health` returns JSON (ok status + service identity).
- DESIGN.md colour, typography, spacing, radius as CSS variables + Tailwind theme; light mode only.
- Brand wordmark: SVG or curated script — never Comic Sans in production.
- No parent/LMS/clinician/EduConnect wireframe product routes.
- All I/O matrix scenarios covered by automated tests that pass before completion.

**Ask First:**
- Initialising git (repo currently has no `.git`).
- Changing Architecture stack majors.

**Never:**
- Auth, tenancy models, Role shell, Capacitor, Domain business logic.
- Dark mode, purple glow, neon, or wireframe family-care screens.
- Marking done without green health + token + IA tests.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Health happy path | `GET /api/v1/health` | `200` JSON `{status, service, version}` for GuidelyEdu API | N/A |
| Health unknown route | `GET /api/v1/does-not-exist` | Non-200 JSON/error (no Blade product shell) | Framework error OK |
| Token CSS exposed | Web token stylesheet | CSS vars for canvas, primary `#5B63E6`, secondary `#10B981`, page `24px`, radius ~`12px`, topbar/sidebar sizes | Fail if missing/wrong |
| Token Tailwind theme | Tailwind/theme config | Theme exposes primary/secondary/canvas for utilities | Fail if keys absent |
| No forbidden IA | Web router | No parent portal, student LMS, clinician EMR, or EduConnect routes | Fail if present |
| README runbook | Root README | Documents run API, web, and queue worker locally | Fail if section missing |

</frozen-after-approval>

## Code Map

- Repo root — greenfield; `_bmad/`, `_bmad-output/`, `.agents/`, `docs/` only (no `apps/`).
- Architecture spine Structural Seed — target `apps/api` + `apps/web`; AD-1 / AD-13.
- `DESIGN.md` — normative token values.
- `epic-1-context.md` — Story 1.1 is foundation only.
- Create: `apps/api` health route + PHPUnit feature tests.
- Create: `apps/web` tokens + Vitest (or equivalent) token/router tests.
- Create: root `README.md` runbook.

## Tasks & Acceptance

**Execution:**
- [x] `apps/api` -- Scaffold Laravel 13 with `GET /api/v1/health` -- AD-1 API surface
- [x] `apps/api/tests` -- Feature tests for health happy path + unknown route -- matrix health
- [x] `apps/web` -- Scaffold Vue 3.5 + Vite 8 + Tailwind 4 (minimal shell, no product IA) -- SPA host
- [x] `apps/web` styles -- Map DESIGN.md tokens to CSS vars + Tailwind theme (light only); SVG/curated brand -- UX-DR1–3
- [x] `apps/web` tests -- Assert tokens/theme and no forbidden IA routes -- matrix tokens + IA
- [x] `README.md` -- Document API, web, queue worker local run -- operator onboarding
- [x] Ignore/workspace files -- Ignore vendor/node_modules/build; keep planning folders

**Acceptance Criteria:**
- Given a product-less repo, when scaffolded, then `apps/api` is Laravel 13 with JSON `/api/v1/health` and `apps/web` is Vue 3.5 + Vite 8 + Tailwind 4.
- Given DESIGN.md, when web styles load, then colour/typography/spacing/radius tokens exist as CSS/Tailwind variables (light only).
- Given the web router, when inspected, then no wireframe parent/LMS/clinician routes exist.
- Given README, when followed, then API, web, and queue worker can be run locally.
- Given verification commands, when run, then all tests pass before completion.

## Design Notes

Local API may use SQLite for scaffold speed; README names PostgreSQL for non-local. Prefer CSS variables consumed by Tailwind 4 `@theme`. Health example: `{"status":"ok","service":"guidely-api","version":"v1"}`.

## Verification

**Commands:**
- `cd apps/api && php artisan test --filter=Health` -- expected: health tests pass
- `cd apps/web && npm test` -- expected: token + IA tests pass
- `cd apps/api && php artisan route:list --path=api/v1/health` -- expected: GET registered
- `cd apps/web && npm run build` -- expected: build succeeds

**Manual checks (if no CLI):**
- README has concrete commands for API, web, and queue worker.

## Suggested Review Order

**API surface**

- Versioned health JSON — AD-1 entry point for all later clients
  [`api.php:5`](../../apps/api/routes/api.php#L5)

- Force JSON errors for `api/*` even without Accept header
  [`app.php:19`](../../apps/api/bootstrap/app.php#L19)

**Design tokens & brand**

- DESIGN.md colours/spacing as single `@theme` source (light only)
  [`tokens.css:8`](../../apps/web/src/styles/tokens.css#L8)

- Wordmark loads shared SVG asset (no Comic Sans stack in component)
  [`BrandWordmark.vue:5`](../../apps/web/src/components/BrandWordmark.vue#L5)

**SPA IA**

- Scaffold routes export — home only, no wireframe product IA
  [`routes.js:7`](../../apps/web/src/router/routes.js#L7)

**Operator runbook**

- Local API, queue worker, and web commands
  [`README.md:19`](../../README.md#L19)

**Tests**

- Health happy path + plain-GET unknown route JSON shell
  [`HealthTest.php:9`](../../apps/api/tests/Feature/HealthTest.php#L9)

- Token values, brand asset wiring, runtime route table, README
  [`scaffold.spec.js:19`](../../apps/web/tests/scaffold.spec.js#L19)
