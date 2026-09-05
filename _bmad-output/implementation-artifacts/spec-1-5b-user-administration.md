---
title: '1.5b User administration APIs (FR-54)'
type: 'feature'
created: '2026-09-05'
status: 'done'
baseline_commit: 'NO_VCS'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/implementation-artifacts/spec-1-5-roles-policies-and-user-administration.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Tenant Admins cannot provision or manage Users — FR-54 (invite/create, list, Role + School scope, password reset, deactivate, last-admin orphan guard) was deferred when Story 1.5 split to Roles/policies only.

**Approach:** Ship Tenant Admin–only `/api/v1/users` JSON APIs on the existing Role/`deactivate()` foundation, with same-Tenant query isolation (BelongsToTenant-equivalent — not the raw trait on Authenticatable), without redoing shipped Roles/policies.

## Boundaries & Constraints

**Always:**
- Active Tenant Admin only for User list/mutations; others 403 `{ message: AccessMessages::FORBIDDEN, code: "forbidden" }` (FR-54, FR-7).
- Create (“invite”): name, email, password, Role, optional same-Tenant School ids; `tenant_id` = Admin’s Tenant; no public signup; no outbound email (FR-5, FR-54).
- List/show/update only current-Tenant Users; never cross-Tenant.
- Update may change Role and/or School scope; Trust Roles only if `trust_dashboard` on; never assign `PlatformOperator` (`Role::isTenantAssignable()`).
- School attach: same-Tenant Schools only (else 422).
- Password reset: Admin sets new hashed password; revoke that User’s Sanctum tokens.
- Deactivate only via `User::deactivate()`; block deactivate or demote from `TenantAdmin` when it would leave zero active Tenant Admins (422 + clear `code`).
- Isolate User admin queries with BelongsToTenant-equivalent (policy + tenant filter/local scope). Do **not** add `BelongsToTenant` to `User` (null-tenant `0=1` breaks login email lookup).
- Mirror Schools controller/FormRequest/Resource pattern under `auth:sanctum` + `active`.
- Feature tests cover matrix; Auth/RolePolicy/Tenant/Flag/Health stay green.

**Ask First:**
- Spatie or new authz/invite packages; outbound invite email/magic-link; Vue Users admin UI (1.8).

**Never:**
- Redo Role enum, School/Tenant/Flag policies, AccessDenied, or `EnsureUserIsActive`.
- Public signup, SSO (1.6), Audit UI (1.7), Pupil school-scope authz beyond pivot assign, Inertia.
- Soft-delete skipping `deactivate()`; allowing last Tenant Admin removal.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Create | Admin POST valid | 201 User + Role/schools | 422 validation |
| Non-admin | Teacher create/list | 403 forbidden | Not 200 |
| List | Admin GET | Same-Tenant Users only | No leaks |
| Update Role/schools | PATCH | 200; pivot synced | Trust/Role invalid → 422 |
| Cross-Tenant school | Foreign school_id | Rejected | 422 |
| Password reset | PATCH password | Hash updated; tokens gone | 422 weak |
| Deactivate | Dedicated action | `deactivate()` | N/A |
| Last Admin | Sole active TenantAdmin | Block deactivate/demote | 422 orphan |
| Extra Admin | ≥2 active TenantAdmins | Self-deactivate OK | N/A |

</frozen-after-approval>

## Code Map

- Read-only continuity: `app/Domain/Identity/Role.php` (`isTenantAssignable`); `app/Models/User.php` L47–107 (`schools`, `isTenantAdmin`, `deactivate`); `AccessMessages::FORBIDDEN`; `routes/api.php` L26 auth group; `SchoolController` + FormRequests + `SchoolResource`; `SchoolPolicy`/`TenantPolicy` (do not rewrite); `UserFactory` Role states; `RolePolicyTest` forbidden helper; `BelongsToTenant` (School only — do not attach to User); `CurrentTenant::id()` from Auth; existing `school_user` pivot.
- Create: `UserPolicy` (Tenant Admin + same tenant); `UserController` + Store/Update/ResetPassword/Deactivate FormRequests; `UserResource` (id, name, email, role, tenant_id, school_ids, deactivated_at; never password); `apiResource('users')` + reset/deactivate routes; tenant query helper/local scope; orphan-guard count of active TenantAdmins; allow setting `role` (currently not fillable).
- Tests: `UserAdministrationTest` for matrix; regression Auth/RolePolicy/Tenant/FeatureFlag/Health.

## Tasks & Acceptance

**Execution:**
- [x] `app/Policies/UserPolicy.php` + register — Tenant Admin + same-tenant authz
- [x] `app/Models/User.php` — tenant query helper; orphan guard; role writable for admin
- [x] `UserController` + FormRequests + `UserResource` + `routes/api.php` — create/list/show/update/reset/deactivate
- [x] `tests/Feature/UserAdministrationTest.php` — I/O matrix
- [x] Regression — Auth/RolePolicy/Tenant/FeatureFlag/Health green

**Acceptance Criteria:**
- Given a Tenant Admin, when they create/list/update/reset/deactivate Users in their Tenant, then FR-54 holds and non-admins are forbidden.
- Given the last active Tenant Admin, when deactivate or demote would orphan the Tenant, then API returns 422.
- Given authenticated Tenant context, when User admin queries run, then only that Tenant’s Users are visible/mutable.
- Given verification commands, when run, then User admin + regression suites pass.

## Design Notes

Invite = create with password (no mailer). Orphan guard counts active `TenantAdmin` for the Tenant before deactivate/demote. Isolation: `where('tenant_id', CurrentTenant::id())` + policy — not the `BelongsToTenant` trait on Authenticatable. Password reset: update hash + `tokens()->delete()`. Schools: `sync()` validated same-tenant ids.

## Verification

**Commands:**
- `php artisan test --compact --filter=UserAdministration` -- expected: matrix green
- `php artisan test --compact --filter='RolePolicy|Authentication|Tenant|FeatureFlag|Health'` -- expected: regression green
- `vendor/bin/pint --dirty --format agent` -- expected: style clean

## Suggested Review Order

**API entry**

- Tenant Admin User CRUD + password reset + deactivate + orphan guards
  [`UserController.php:1`](../../app/Http/Controllers/Api/V1/UserController.php#L1)

- Routes under `auth:sanctum` + `active`
  [`api.php:40`](../../routes/api.php#L40)

**Authz**

- Tenant Admin + same-tenant UserPolicy
  [`UserPolicy.php:1`](../../app/Policies/UserPolicy.php#L1)

**Model**

- Tenant scope, orphan helpers, deactivate; role not fillable
  [`User.php:1`](../../app/Models/User.php#L1)

**Tests**

- FR-54 matrix including last-admin and isolation
  [`UserAdministrationTest.php:1`](../../tests/Feature/UserAdministrationTest.php#L1)
