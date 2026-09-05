---
title: '1.2 Tenant, School, and isolation'
type: 'feature'
created: '2026-09-05'
status: 'done'
baseline_commit: 'NO_VCS'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/implementation-artifacts/spec-1-1-root-laravel-scaffold-and-design-tokens.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Root Laravel has no Tenant/School model or isolation — later stories cannot safely store Tenant-owned data.

**Approach:** Add `App\Domain\Tenancy` with Tenant and School (ULIDs; `tenant_id` on Schools and Users), default query/policy scoping to the current Tenant (AD-2), Trust multi-School attach (FR-2), and School activation plus Tenant named-cohort rollout flag (FR-3). Expose minimal `/api/v1` JSON for Schools and Tenant cohort under the current Tenant. Prove cross-Tenant id access returns 404 (preferred) or 403 with no leakage (FR-1) via Feature tests using `actingAs` (full Sanctum login is Story 1.4).

## Boundaries & Constraints

**Always:**
- Every Tenant-owned row stores `tenant_id`; queries/policies default to current Tenant (AD-2).
- Public IDs are ULID/UUID strings in API JSON.
- Cross-Tenant show/update/delete → consistent 404/403 JSON without leaking foreign existence.
- Trust Tenant may have many Schools; single-School Tenant allowed (FR-2).
- Schools have `is_active`; inactive Schools omitted from active-school listings (FR-3 Teacher-picker exclusion). Tenant stores optional named cohort label/flag for later Pupil rollout (no Pupil CRUD).
- Domain under `app/Domain/Tenancy`; JSON API only — no Inertia; no Vue product IA beyond what’s already scaffolded.
- Automated tests cover every matrix row and must pass before completion.

**Ask First:**
- Changing cross-Tenant failure mode away from “404 preferred / 403 allowed, no leakage”.
- Shipping public login endpoints (belongs in 1.4).

**Never:**
- Feature-flag product surface (1.3), Sanctum login routes (1.4), Roles/permission matrix UI (1.5), Pupils/Evidence.
- Global unscoped School queries on product paths.
- Distinct error bodies that confirm Tenant B existence to Tenant A Users.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| List schools own tenant | User in Tenant A, ≥1 School | `200` JSON list only Tenant A Schools | N/A |
| Cross-tenant show by id | User A requests School id owned by Tenant B | `404` (preferred) or `403` JSON; no Tenant B payload | No existence leak |
| Cross-tenant mutate | User A PATCH/DELETE Tenant B School | `404`/`403`; Tenant B row unchanged | Same |
| Trust multi-school | Trust Tenant creates 2+ Schools | Both share that `tenant_id`; list returns both | 422 on invalid payload |
| Disable school | Deactivate School X | Omitted from active-schools list; still Tenant-scoped for admin by id if exposed | N/A |
| Cohort rollout flag | Tenant sets named cohort label/flag | Persisted; readable on Tenant resource | 422 if invalid |
| Unauthenticated | No acting User on school list | `401` JSON (not HTML SPA shell) | Framework authz |

</frozen-after-approval>

## Code Map

- Continuity 1.1: root Laravel 13; `/api/v1/health`; JSON `api/*` errors; SPA shell in `routes/web.php`.
- `app/Models/User.php` — add `tenant_id` ULID FK (Roles later).
- Create: `app/Domain/Tenancy` models, BelongsToTenant scope, current-tenant from auth User, policies, API Resources, `routes/api.php` v1 school/tenant endpoints, factories, `tests/Feature` isolation suite.

## Tasks & Acceptance

**Execution:**
- [x] Migrations + Domain\Tenancy -- Tenant (school\|trust + cohort flag), School (`tenant_id`, name, `is_active`), User.`tenant_id`
- [x] Tenant scope + current-tenant from auth User -- AD-2 default scoping
- [x] API v1 Schools (+ Tenant cohort read/update) -- ULID JSON Resources, current-Tenant only
- [x] Policies -- cross-Tenant show/update/delete → 404/403 no leak
- [x] Feature tests -- every matrix row via `actingAs` (no login routes)
- [x] Factories -- two-Tenant fixtures

**Acceptance Criteria:**
- Given two Tenants with Schools, when Tenant A User requests Tenant B School by id, then 404/403 with no leakage (FR-1).
- Given a Trust Tenant with multiple Schools, when listed, then all share that `tenant_id` (FR-2).
- Given School deactivation or Tenant cohort flag, when active list / Tenant is read, then inactive Schools are omitted from active list and cohort persists (FR-3).
- Given Tenant-owned rows on product paths, when queried, then `tenant_id` is set and scopes to current Tenant (AD-2).
- Given verification commands, when run, then all matrix tests pass.

## Design Notes

Use `actingAs($user)` with `$user->tenant_id` for isolation proofs — no Sanctum login routes. Prefer **404** for cross-Tenant show-by-id. Active-schools filter (`?active=1` or dedicated route) satisfies Teacher-picker exclusion without Teacher UX.

## Verification

**Commands:**
- `php artisan test --filter=Tenant` -- expected: isolation tests pass
- `php artisan test --filter=Health` -- expected: health still green
- `php artisan route:list --path=api/v1` -- expected: tenant/school routes under v1

**Manual checks (if no CLI):**
- No Inertia; Tenancy under `app/Domain/Tenancy`; no live `apps/` product tree.

## Suggested Review Order

**Domain isolation**

- Global tenant scope + create-time tenant_id assignment
  [`BelongsToTenant.php:17`](../../app/Domain/Tenancy/Concerns/BelongsToTenant.php#L17)

- Current tenant from authenticated User
  [`CurrentTenant.php:14`](../../app/Domain/Tenancy/CurrentTenant.php#L14)

**API**

- Auth-gated schools + tenant cohort routes
  [`api.php:16`](../../routes/api.php#L16)

- School policy same-tenant checks
  [`SchoolPolicy.php:51`](../../app/Policies/SchoolPolicy.php#L51)

**Tests**

- Full isolation matrix including injected tenant_id ignored
  [`TenantIsolationTest.php:15`](../../tests/Feature/TenantIsolationTest.php#L15)
