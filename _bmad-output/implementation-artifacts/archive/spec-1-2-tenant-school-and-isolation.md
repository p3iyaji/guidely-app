---
title: '1.2 Tenant, School, and isolation'
type: 'feature'
created: '2026-09-05'
status: 'draft'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/implementation-artifacts/spec-1-1-monorepo-scaffold-and-design-tokens.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** After the monorepo scaffold, there is no Tenant/School model or isolation — later stories cannot safely store Tenant-owned data.

**Approach:** Add Domain\Tenancy with Tenant and School (ULIDs, `tenant_id` on Schools and Users), default query/policy scoping to the current Tenant (AD-2), Trust multi-School attach (FR-2), and School activation / named-cohort rollout flags (FR-3). Expose minimal versioned JSON API for Schools under the current Tenant. Prove cross-Tenant id access returns 403/404 with no leakage (FR-1) via feature tests (actingAs Users with `tenant_id` — full login is Story 1.4).

## Boundaries & Constraints

**Always:**
- Every Tenant-owned row stores `tenant_id`; queries/policies default to current Tenant (AD-2).
- Public IDs are ULID/UUID strings — never sequential Pupil/School ids exposed cross-Tenant.
- API errors JSON `{ message, code?, errors? }`; cross-Tenant access → 403 or 404 without confirming foreign existence.
- Trust Tenant may have many Schools; single-School Tenant allowed (FR-2).
- Incremental activation: Schools have an enabled/active flag; disabled Schools excluded from “active school” listings used for Teacher pickers (FR-3). Named Pupil cohort rollout stored as a Tenant-level optional cohort label/flag for later Pupil stories (no Pupil CRUD here).
- Domain layout under `apps/api/app/Domain/Tenancy` (or equivalent) per Architecture; Presentation remains Vue-only — no product IA for parents/LMS.
- Automated tests cover every I/O matrix row and must pass before completion.

**Ask First:**
- Changing cross-Tenant failure mode away from “403 or 404 without leakage”.
- Introducing full Sanctum login endpoints (belongs in 1.4).

**Never:**
- Feature flags product surface (1.3), auth/session/token login (1.4), Roles/permission matrix UI (1.5), Pupils/Evidence, Vue Role shell.
- Global unscoped School/User queries in product code paths.
- Leaking Tenant B existence via distinct error bodies when Tenant A guesses ids.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| List schools own tenant | User in Tenant A, ≥1 School | `200` JSON list only Tenant A Schools | N/A |
| Cross-tenant show by id | User Tenant A requests School id belonging to Tenant B | `403` or `404` JSON; no Tenant B payload fields | No existence leak |
| Cross-tenant mutate | User Tenant A PATCH/DELETE Tenant B School id | `403` or `404`; Tenant B row unchanged | Same |
| Trust multi-school | Trust Tenant creates/attaches 2+ Schools | Both Schools share that `tenant_id`; list returns both | Validation 422 on bad payload |
| Disable school | Tenant Admin deactivates School X | School X omitted from active-schools listing; still Tenant-scoped by id for admin if exposed | Inactive not in Teacher-picker list endpoint |
| Cohort rollout flag | Tenant sets named cohort label/flag | Stored on Tenant; readable on Tenant resource | 422 if invalid shape |
| Unauthenticated school list | No acting User | `401` or `403` JSON (not HTML) | Framework authz |

</frozen-after-approval>

## Code Map

- Continuity 1.1: Laravel 13 `/api/v1/health`; SQLite local; JSON `api/*` errors.
- `User.php` — add `tenant_id` ULID FK (Roles later in 1.5).
- Create: Domain\Tenancy, BelongsToTenant scope, policies, v1 school/tenant routes + Resources, Feature tests + factories.

## Tasks & Acceptance

**Execution:**
- [ ] Migrations + Domain\Tenancy -- Tenant (school|trust + cohort flag), School (`tenant_id`, name, `is_active`), User.`tenant_id`
- [ ] Tenant scope + current-tenant from auth User -- AD-2 default scoping
- [ ] API v1 Schools (+ Tenant cohort read/update) -- ULID JSON Resources, current-Tenant only
- [ ] Policies -- cross-Tenant show/update/delete → 403/404 no leak
- [ ] Feature tests -- every matrix row via actingAs (no login routes)
- [ ] Factories/seed helpers -- two-Tenant fixtures

**Acceptance Criteria:**
- Given two Tenants with Schools, when Tenant A User requests Tenant B School by id, then 403/404 with no leakage (FR-1).
- Given a Trust Tenant with multiple Schools, when listed, then all share that `tenant_id` (FR-2).
- Given School deactivation or Tenant cohort flag, when active list / Tenant is read, then inactive Schools are omitted from active list and cohort persists (FR-3).
- Given Tenant-owned rows, when queried on product paths, then `tenant_id` is set and scopes to current Tenant (AD-2).
- Given verification commands, when run, then all matrix tests pass.

## Design Notes

Use `actingAs($user)` with `$user->tenant_id` for isolation proofs — no Sanctum login routes (1.4). Prefer consistent 404 for cross-Tenant show-by-id to reduce existence leaks. Active-schools filter satisfies Teacher-picker exclusion without Teacher UX.

## Verification

**Commands:**
- `cd apps/api && php artisan test --filter=Tenant` -- expected: isolation tests pass
- `cd apps/api && php artisan test --filter=Health` -- expected: health still green
- `cd apps/api && php artisan route:list --path=api/v1` -- expected: tenant/school routes under v1

**Manual checks (if no CLI):**
- No Vue parent/LMS routes; Tenancy lives under `apps/api`.
