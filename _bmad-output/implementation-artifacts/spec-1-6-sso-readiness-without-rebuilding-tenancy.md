---
title: '1.6 SSO readiness without rebuilding tenancy'
type: 'feature'
created: '2026-09-05'
status: 'done'
baseline_commit: '900bee5d4ffc3dbae69a2a1cc662a1f8a544de7a'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/implementation-artifacts/spec-1-5b-user-administration.md'
  - '{project-root}/_bmad-output/planning-artifacts/architecture/architecture-guidely-app-2026-08-20/ARCHITECTURE-SPINE.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Password auth works, but there are no `external_id` or Tenant SSO config fields — connecting School/Trust SSO later would force a tenancy/identity redesign (FR-9, AD-18).

**Approach:** Add User `external_id`, Tenant SSO config stubs (no live IdP), an email-match link helper so future SSO joins existing Users without duplicates, expose stubs on Tenant Admin APIs, and document that SAML vs OIDC remains an Architecture open question.

## Boundaries & Constraints

**Always:**
- Users store nullable unique-per-tenant (or globally unique) `external_id` for IdP subject mapping (FR-9, AD-18).
- Tenant SSO config stub persisted (e.g. `sso_enabled` default false + placeholder provider metadata fields) without requiring a live IdP in Pilot.
- Future enablement must resolve Users by **email** (case-insensitive) and must not create a second account when email already exists; link/`external_id` update path covered by a domain helper + tests.
- Tenant Admin can read/update SSO stub via `/api/v1` (same authz pattern as Tenant cohort); non-admins 403 forbidden.
- UserResource may expose `external_id` (nullable); Tenant Admin may set/clear it on create/update with uniqueness validation.
- Password login/token paths remain primary; no SAML/OIDC callback or Socialite/live IdP in this story.
- README (or `docs/`) states SAML vs OIDC choice remains open for first Trust enablement (ARCHITECTURE-SPINE open Q #2).
- Tests cover matrix; Auth/UserAdministration/Tenant/RolePolicy regressions green.

**Ask First:**
- Installing Socialite, SAML packages, or choosing SAML vs OIDC for implementation.
- Making `external_id` globally unique across all Tenants if product requires Tenant-scoped uniqueness instead (default: unique within Tenant when set).

**Never:**
- Live IdP login, ACS/callback routes, or rebuilding Tenants/Roles.
- Public signup; Audit product UI (1.7); Role shell (1.8); Inertia.
- Auto-enabling SSO (`sso_enabled` stays false by default).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Store external_id | Admin create/update User with external_id | Persisted; returned on UserResource | 422 duplicate in Tenant |
| Clear external_id | Admin sets null | Cleared | N/A |
| SSO stub read | Admin GET Tenant (or dedicated SSO endpoint) | Stub fields present; sso_enabled false by default | 403 non-admin |
| SSO stub update | Admin PATCH stub metadata / enable flag | Persisted without IdP call | 422 invalid body |
| Email match link | Helper: email exists → attach external_id; email new → create path stubbed/tested | No duplicate User for same email | Conflict if external_id already on another User |
| Password auth unchanged | Login/token with password | Still works | N/A |
| Docs | README/docs open question | States SAML vs OIDC undecided for first Trust | N/A |

</frozen-after-approval>

## Code Map

- Continuity: `User` + `UserFactory`/`UserController`/`UserResource` (1.5b); `Tenant` + `TenantController`/`TenantResource`/`TenantPolicy`; `AuthController` email-lowered login/token; `ARCHITECTURE-SPINE.md` L179–183, L311 open Q #2; root `README.md`.
- Migrate: `users.external_id` nullable string + unique index scoped by `tenant_id` (composite unique where not null); Tenant SSO stub columns (or JSON `sso_config`) — `sso_enabled` bool default false + stub fields (provider, entity_id/client_id placeholders — no secrets required in Pilot).
- Domain: `App\Domain\Identity` SSO link helper (match by email, set external_id, refuse duplicate accounts).
- API: expose stub on Tenant show/update (or `/api/v1/tenant/sso`); User create/update accept `external_id`; Resource fields.
- Docs: README section “SSO readiness” pointing to Architecture open question.
- Tests: `SsoReadinessTest` (or extend Tenant/UserAdministration) for matrix; do not break password auth tests.

## Tasks & Acceptance

**Execution:**
- [x] Schema -- User `external_id` + Tenant SSO stub fields
- [x] Domain helper -- email-match link without duplicate Users
- [x] API -- Tenant Admin SSO stub read/update; User `external_id` on admin APIs/Resources
- [x] Docs -- README states SAML vs OIDC still open
- [x] Tests -- matrix + Auth/UserAdministration/Tenant regressions

**Acceptance Criteria:**
- Given password auth works, when SSO readiness lands, then Users store `external_id` and Tenants store SSO stubs without a live IdP (FR-9).
- Given an existing User email, when SSO link helper runs, then no duplicate account is created.
- Given documentation, when reviewed, then SAML vs OIDC remains an explicit open question for first Trust enablement.
- Given verification commands, when run, then matrix and regression tests pass.

## Design Notes

Prefer composite unique `(tenant_id, external_id)` ignoring nulls (DB partial unique if SQLite/Postgres allow; otherwise app-level uniqueness validation). Stub fields are placeholders only — no encrypted client secrets required this story. Do not implement IdP redirect/callback. Email match uses the same lowercasing as AuthController.

## Verification

**Commands:**
- `php artisan test --compact --filter=SsoReadiness` -- expected: matrix green
- `php artisan test --compact --filter='Authentication|UserAdministration|Tenant|RolePolicy'` -- expected: regression green
- `vendor/bin/pint --dirty --format agent` -- expected: style clean

**Manual checks (if no CLI):**
- No live IdP routes; README mentions SAML vs OIDC open; `sso_enabled` defaults false.

## Suggested Review Order

**Domain**

- Email-match link without duplicates; no overwrite / deactivated skip
  [`LinkExternalIdByEmail.php:1`](../../app/Domain/Identity/LinkExternalIdByEmail.php#L1)

**API**

- Tenant Admin SSO stub read/update (no live IdP)
  [`TenantSsoController.php:1`](../../app/Http/Controllers/Api/V1/TenantSsoController.php#L1)

- Routes for `/tenant/sso`
  [`api.php:1`](../../routes/api.php#L1)

**Schema / model**

- Tenant SSO stub defaults off
  [`Tenant.php:18`](../../app/Domain/Tenancy/Tenant.php#L18)

**Docs**

- SAML vs OIDC open question
  [`README.md:80`](../../README.md#L80)

**Tests**

- Matrix including link helper edge cases
  [`SsoReadinessTest.php:1`](../../tests/Feature/SsoReadinessTest.php#L1)
