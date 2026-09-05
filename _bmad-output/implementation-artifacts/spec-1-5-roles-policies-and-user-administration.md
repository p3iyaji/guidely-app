---
title: '1.5 Roles, policies, and AccessDenied (split)'
type: 'feature'
created: '2026-09-05'
status: 'done'
baseline_commit: 'NO_VCS'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/implementation-artifacts/spec-1-4-authentication-for-web-and-hybrid.md'
  - '{project-root}/_bmad-output/planning-artifacts/prds/prd-guidely-app-2026-08-16/addendum.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Any authenticated Tenant User can mutate Schools, cohort, and feature flags because there are no Roles or deactivation — least privilege (FR-6, FR-7) cannot be enforced.

**Approach:** Add GuidelyEdu Roles (Trust Roles only when Trust features on), School scope on Users, deactivation that immediately blocks auth/API, tighten School/Tenant/Flag policies to Tenant Admin, and ship Vue AccessDenied (“You don’t have access”). User admin CRUD APIs are deferred.

## Boundaries & Constraints

**Always:**
- Roles on User: Teacher, Support Staff, SENCO, School Leader, Tenant Admin; Trust SEND Lead / Trust Executive only when `trust_dashboard` is on; Platform Operator is not a Tenant Role (may exist with null `tenant_id`; not assignable via Tenant flows this story) (FR-6).
- Addendum matrix drives API authz for **current** surfaces: Schools mutate, Tenant cohort, feature-flag update → Tenant Admin only (FR-7). Vue never grants access.
- Users may attach School scope (pivot) for later Pupil assignment; factories/states cover each Role.
- `deactivated_at` set → cannot login/token; Sanctum rejects deactivated Users (revoke tokens when deactivated in-process if a path sets it).
- Forbidden API: 403 `{ message: "You don’t have access.", code: "forbidden" }`.
- Vue `AccessDenied` with exact copy “You don’t have access” (UX-DR18).
- Tests cover every matrix row; update prior Feature tests to use Tenant Admin actors for mutations; Auth/Tenant/Flag/Health green.

**Ask First:**
- Adding Spatie/laravel-permission (or any new authz package).
- Shipping User admin CRUD in this story (deferred by split).

**Never:**
- User invite/create/list/update/reset/deactivate HTTP APIs or last-admin orphan guard (deferred).
- Public signup, SSO (1.6), full Audit UI (1.7), Role shell chrome (1.8), Inertia.
- Letting non–Tenant Admin mutate flags, cohort, or Schools.
- Implementing Pupil/Evidence policies beyond optional deny-by-default Gates.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Tenant Admin mutates | User Role=Tenant Admin PATCH flags/schools/cohort | 200 as today | N/A |
| Non-admin mutates | Teacher (or Support/SENCO/Leader) PATCH same | 403 `forbidden` + AccessDenied message | Not 200 |
| Deactivated login/token | User with `deactivated_at` set | Same generic auth failure as bad password; no session/token | No email leak |
| Deactivated API | Previously valid session/token for deactivated User | 401/403; cannot call protected API | Immediate block |
| Trust Role + flag off | Factory/helpers must not treat Trust Roles as valid when flag off for policy helpers | Trust Roles gated by `trust_dashboard` | Documented in Design Notes |
| AccessDenied Vue | Component/page unit-tested | Shows “You don’t have access” | N/A |
| Prior actingAs | Existing Tenant/Flag tests | Pass using Tenant Admin Role on mutating actors | Update fixtures |

</frozen-after-approval>

## Code Map

- Continuity: `auth:sanctum` (`routes/api.php`); `AuthController`; `User`/`UserFactory::forTenant`; `SchoolPolicy`/`TenantPolicy` same-tenant only; Schools/Tenant/FeatureFlag FormRequests authorize those policies; `FeatureFlagResolver` + `trust_dashboard`; Sanctum + Audit from 1.4.
- Create: `App\Domain\Identity\Role` enum; migration `role` + `deactivated_at` on users; `school_user` pivot; factory Role states; `User::isDeactivated()` / `isTenantAdmin()` helpers as needed.
- Auth: reject deactivated in login/token; middleware or Authenticate extension blocks deactivated Sanctum users.
- Policies: tighten `SchoolPolicy` create/update/delete and `TenantPolicy` update to Tenant Admin; keep viewAny/view as appropriate for same-tenant read or Admin-only if matrix says no Pupil content for Admin — for **current** school list, allow any active same-tenant staff read; mutations Admin-only.
- Optional: Gate abilities mirroring addendum future rows (deny-by-default).
- Vue: `AccessDenied` page + `/access-denied` route + Vitest.
- Tests: `RolePolicyTest` (or similar) for matrix; update `TenantIsolationTest`/`FeatureFlagTest` actors to Tenant Admin; Authentication tests for deactivated Users.
- No `/api/v1/users` routes this story.

## Tasks & Acceptance

**Execution:**
- [x] Identity schema -- Role, deactivated_at, School scope pivot; factory Role states
- [x] Auth hardening -- deactivated cannot login/token or use API
- [x] Policies -- Schools/Tenant/Flags mutations require Tenant Admin; 403 forbidden shape
- [x] Vue AccessDenied -- “You don’t have access” + Vitest
- [x] Tests -- matrix + update prior mutation tests; Auth/Tenant/Flag/Health green

**Acceptance Criteria:**
- Given Role set on Users, when non-admins hit mutating Tenant APIs, then API returns forbidden (FR-6, FR-7).
- Given a deactivated User, when they authenticate or call the API, then access is blocked immediately.
- Given AccessDenied Vue, when rendered, then copy is “You don’t have access” (UX-DR18).
- Given verification commands, when run, then matrix and regression tests pass.

## Design Notes

First-party Role enum; no Spatie. Default new factory Users to `TenantAdmin` only where tests need mutations — otherwise explicit states (`teacher()`, `tenantAdmin()`, etc.). Trust Roles: enum cases exist; `Role::isTrustRole()` + require `trust_dashboard` before treating as active Trust staff in helpers. Deactivation for tests: factory state `deactivated()` setting `deactivated_at`; production deactivate API deferred. Forbidden JSON aligns with UX copy.

## Verification

**Commands:**
- `php artisan test --filter=RolePolicy` -- expected: Role/policy matrix pass
- `php artisan test --filter=Authentication` -- expected: deactivated blocked; auth green
- `php artisan test --filter=Tenant` -- expected: green with Admin actors
- `php artisan test --filter=FeatureFlag` -- expected: green with Admin actors
- `php artisan test --filter=Health` -- expected: green
- `npm test` -- expected: AccessDenied (+ prior) pass

**Manual checks (if no CLI):**
- Teacher cannot PATCH feature flags; AccessDenied copy exact; no User admin routes.

## Suggested Review Order

**Identity**

- Role enum including Trust + Platform Operator boundaries
  [`Role.php:5`](../../app/Domain/Identity/Role.php#L5)

- Active staff / Trust gating / deactivate + token revoke
  [`User.php:66`](../../app/Models/User.php#L66)

**Policies**

- Tenant Admin–only School mutations; staff reads
  [`SchoolPolicy.php:30`](../../app/Policies/SchoolPolicy.php#L30)

- Tenant Admin–only cohort/flag updates
  [`TenantPolicy.php:1`](../../app/Policies/TenantPolicy.php#L1)

**Auth gate**

- Deactivated Users blocked on protected API (logout + revoke)
  [`EnsureUserIsActive.php:11`](../../app/Http/Middleware/EnsureUserIsActive.php#L11)

**Vue**

- UX-DR18 AccessDenied copy
  [`AccessDenied.vue:1`](../../resources/js/pages/AccessDenied.vue#L1)

**Tests**

- Role/policy matrix including Trust flag gating
  [`RolePolicyTest.php:1`](../../tests/Feature/RolePolicyTest.php#L1)
