---
title: '1.3 Feature flags by configuration'
type: 'feature'
created: '2026-09-05'
status: 'done'
baseline_commit: 'NO_VCS'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/implementation-artifacts/spec-1-2-tenant-school-and-isolation.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Tenancy exists but product surfaces cannot be gated per Tenant — Pilots would see Trust/advanced capabilities or empty “perfect” aggregates.

**Approach:** Store feature flags in DB per Tenant (optional School override), resolve them for the current Tenant, gate flagged API routes so disabled features return an explicit **not-available** JSON response (never empty success aggregates), expose flag state on the Tenant API for the SPA, and add a Vue `FeatureFlaggedEmpty` “Not available for this Tenant” pattern (FR-4, AD-8, UX-DR17).

## Boundaries & Constraints

**Always:**
- Flags live in DB configuration (Tenant-scoped; School override optional), not env/deploy hardcoding (FR-4).
- Disabled flagged capability → explicit not-available response with stable `code` (e.g. `feature_not_available`) and UK English message — **not** `200` with empty charts/lists implying coverage (AD-8, UX-DR17).
- Initial flag keys (at minimum): `trust_dashboard`, `connectors`, `advanced_documentation_packs` (tribunal/inspection packs).
- Default for new Tenants: flagged advanced surfaces **off** (Pilot-safe).
- Tenant Admin can read/update flags for their Tenant via `/api/v1` (actingAs until 1.4 login).
- Vue includes `FeatureFlaggedEmpty` shared component with copy “Not available for this Tenant”.
- Automated tests cover every matrix row and must pass before completion.

**Ask First:**
- Adding commercial flag keys beyond the three named above in this story.
- Changing not-available HTTP status away from **403** (preferred) or **404** if a strong reason appears.

**Never:**
- Implementing full Trust Dashboard / Connector sync / tribunal pack generation (later epics) — only stubs/gates that prove not-available vs enabled behaviour.
- Sanctum login (1.4), Roles matrix (1.5), Inertia.
- Returning empty successful aggregates when a flag is off.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Flag off → gated route | Tenant `trust_dashboard=false`; auth User hits Trust Dashboard stub endpoint | Explicit not-available JSON (`code: feature_not_available`); not 200 empty data | 403 preferred |
| Flag on → gated route | Same Tenant flag enabled | Stub endpoint returns 200 with explicit placeholder payload (proves gate opens) | N/A |
| List/read flags | Auth User GET Tenant flags | JSON of known keys + enabled booleans for current Tenant | 401 if unauthenticated |
| Update flag | Auth User PATCH enable/disable a known key | Persisted; subsequent gated call respects new value | 422 unknown key / invalid body |
| School override (optional) | School-level override for a flag | Resolution prefers School override when present for school-scoped call; else Tenant | Documented in Resource |
| Default new Tenant | Newly created Tenant | Advanced flags default **false** | N/A |
| Vue empty pattern | FeatureFlaggedEmpty rendered / unit-tested | Shows “Not available for this Tenant” (or exact EXPERIENCE wording) | N/A |
| Cross-tenant flags | User A cannot read/update Tenant B flags | 404/403; no leakage (inherits 1.2 isolation) | Same as tenancy |

</frozen-after-approval>

## Code Map

- Continuity: `app/Domain/Tenancy` Tenant/School; `/api/v1/tenant` + schools; `actingAs` auth; JSON `api/*` errors.
- Create: feature flag model/migration under Tenancy; resolver service; middleware or route-group gate; stub Trust/Connector/advanced pack routes; extend Tenant resource; Vue `resources/js/shared/ui/FeatureFlaggedEmpty.vue` + Vitest; Feature tests for matrix.

## Tasks & Acceptance

**Execution:**
- [x] DB + Domain feature flags -- Tenant (and optional School) flag rows for the three keys; defaults off
- [x] Flag resolver + gate -- disabled → `feature_not_available` JSON; enabled → stub 200
- [x] API -- GET/PATCH flags for current Tenant; stub gated endpoints for the three capabilities
- [x] Tenant Resource includes flag map for SPA
- [x] Vue `FeatureFlaggedEmpty` + test -- EXPERIENCE not-available copy
- [x] Feature tests -- every matrix row (incl. isolation + defaults)

**Acceptance Criteria:**
- Given Trust Dashboard flag off, when a User hits the gated Trust endpoint, then API returns explicit not-available (not empty aggregates) (FR-4, AD-8).
- Given flags in DB per Tenant(/School), when updated via API, then gated routes respect the new values without redeploy.
- Given the Vue shared UI, when FeatureFlaggedEmpty is used, then it shows “Not available for this Tenant” (UX-DR17).
- Given verification commands, when run, then all matrix tests pass.

## Design Notes

Prefer HTTP **403** with body `{ message, code: "feature_not_available", feature: "<key>" }`. Stub enabled responses must be clearly placeholders (e.g. `{ available: true, feature: "trust_dashboard" }`), not fake KPI zeros. School overrides are optional in this story but if implemented must be tested; if deferred, document as deferred-work and keep Tenant-only.

## Deferred Work

- **School-level feature flag overrides:** Deferred in 1.3. Resolution is **Tenant-only** via `FeatureFlagResolver` / `tenant_feature_flags`. Prefer School override when present for school-scoped calls in a later story if product needs it; until then do not add School override rows or resolution branches.

## Verification

**Commands:**
- `php artisan test --filter=FeatureFlag` -- expected: flag matrix tests pass
- `php artisan test --filter=Tenant` -- expected: tenancy still green
- `php artisan test --filter=Health` -- expected: health still green
- `npm test` -- expected: FeatureFlaggedEmpty (+ prior scaffold) tests pass

**Manual checks (if no CLI):**
- No Inertia; flags not hardcoded only in `.env`.

## Suggested Review Order

**Domain & gate**

- Flag keys + Tenant-scoped storage defaults off
  [`FeatureFlagKey.php`](../../app/Domain/Tenancy/FeatureFlagKey.php)

- Middleware returns `feature_not_available` when off
  [`EnsureFeatureIsEnabled.php`](../../app/Http/Middleware/EnsureFeatureIsEnabled.php)

**API**

- Flag read/update + gated stubs
  [`api.php:22`](../../routes/api.php#L22)

**Vue**

- EXPERIENCE empty pattern
  [`FeatureFlaggedEmpty.vue`](../../resources/js/shared/ui/FeatureFlaggedEmpty.vue)

**Tests**

- Matrix coverage for off/on/defaults/isolation
  [`FeatureFlagTest.php:17`](../../tests/Feature/FeatureFlagTest.php#L17)
