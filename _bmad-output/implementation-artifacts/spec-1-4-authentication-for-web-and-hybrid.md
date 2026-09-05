---
title: '1.4 Authentication for Web and Hybrid'
type: 'feature'
created: '2026-09-05'
status: 'done'
baseline_commit: 'NO_VCS'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/_bmad-output/implementation-artifacts/spec-1-3-feature-flags-by-configuration.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** APIs are protected only by test `actingAs` session auth — there is no real Web sign-in, Hybrid token path, or logout, so staff cannot securely access their Tenant from browser or device (FR-8, AD-18).

**Approach:** Install Sanctum and ship GuidelyEdu-managed password auth: Web SPA cookie/session (CSRF + login/logout), Hybrid personal access tokens (issue/revoke) on the same User model, generic failed-login responses that emit minimal Audit Events, and a Vue login surface — with no public self-registration (FR-5, FR-10).

## Boundaries & Constraints

**Always:**
- Web = Sanctum SPA cookie/session; Hybrid = Sanctum API tokens; same `User` model (AD-18). Roles matrix stays Story 1.5.
- Protected `/api/v1/*` (except health + auth endpoints) accept either session or token via `auth:sanctum`.
- Failed login returns a generic UK English message and must not reveal whether the email exists; successful and failed auth attempts emit Audit Events (FR-10).
- Logout ends the Web session; Hybrid logout/revoke invalidates the current token (or all tokens for that client path as designed and tested).
- No public self-registration endpoint or UI (FR-5). Users are provisioned (factories / future 1.5 admin).
- Tenancy isolation and `feature:` gates remain intact; existing Feature/Tenant tests stay green (update auth middleware only as needed).
- Automated tests cover every matrix row and must pass before completion.

**Ask First:**
- Changing failed-login HTTP status away from **401** with a stable generic `code` (e.g. `authentication_failed`).
- Issuing long-lived Hybrid tokens without an expiry policy if product needs a non-default lifetime.
- Expanding Audit beyond auth login/logout/fail events (full Audit UI/retention = Story 1.7).

**Never:**
- Public signup, OAuth/SSO live IdP (1.6), Role policies / User admin APIs (1.5), full authenticated shell IA (1.8), Inertia.
- Weakening BelongsToTenant or feature-flag not-available behaviour.
- Returning different messages/status for unknown vs wrong-password emails.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Web login success | Valid email/password; CSRF cookie obtained | Session established; subsequent `auth:sanctum` API calls authorised; Audit Event for success | N/A |
| Web login failure | Wrong password or unknown email | Generic 401; identical shape for both cases; Audit Event for failure | No email enumeration |
| Web logout | Authenticated session | Session ended; further API calls 401 | N/A |
| Hybrid token issue | Valid credentials via token endpoint | Sanctum token returned; Bearer token authorises `/api/v1` | 401 generic on bad creds + Audit fail |
| Hybrid revoke | Authenticated with token; logout/revoke | Token invalidated; further Bearer calls 401 | N/A |
| Unauthenticated API | No session/token on protected route | 401 JSON | N/A |
| No self-registration | POST/GET any public register path | Route absent (404) or explicitly rejected — no create-user public API | FR-5 |
| Vue login | User submits credentials on Login page | CSRF + login flow works; on success navigates into app; UK English copy | Shows generic error on fail |
| Prior features | Tenant/flag tests with `actingAs` | Still pass under `auth:sanctum` | Update tests only if middleware swap requires it |

</frozen-after-approval>

## Code Map

- Continuity: `User` + `UserFactory::forTenant`; `CurrentTenant` from `Auth::user()->tenant_id`; `routes/api.php` `auth` group + `feature:`; JSON `api/*` errors in `bootstrap/app.php`; SPA shell `routes/web.php` + `resources/views/app.blade.php`; Vue `resources/js/router/index.js`, `HomePage.vue`.
- Install: `laravel/sanctum` — publish config + `personal_access_tokens` migration; `User` add `HasApiTokens`.
- Wire: `bootstrap/app.php` — Sanctum stateful API middleware; alias/use `auth:sanctum` on protected API routes.
- Create Domain Audit stub: `app/Domain/Audit/` append-only writer + migration for auth events only (login success/fail/logout); no product purge UI.
- Create: Auth API controller(s) under `app/Http/Controllers/Api/V1/` — CSRF cookie route (Sanctum), login, logout, token issue, token revoke; Form Requests; no register.
- Vue: `resources/js/pages/LoginPage.vue`, router `/login`, thin auth API helper (`fetch` + credentials + CSRF); Vitest for generic error / route presence.
- Tests: `tests/Feature/AuthenticationTest.php` covering matrix; keep `TenantIsolationTest` / `FeatureFlagTest` / `HealthTest` green.

## Tasks & Acceptance

**Execution:**
- [x] Sanctum install + User tokens -- package, config, migration, `HasApiTokens`
- [x] API auth middleware -- stateful SPA + `auth:sanctum` on protected `/api/v1` (health + auth endpoints public as designed)
- [x] Auth endpoints -- login/logout (session), token issue/revoke (Hybrid); CSRF cookie; no register
- [x] Audit emitter -- append-only auth success/fail/logout events
- [x] Vue LoginPage + router + credentialed API helper + Vitest
- [x] Feature tests -- every matrix row; regression on Tenant/Flag/Health

**Acceptance Criteria:**
- Given a provisioned User with valid credentials, when they sign in via Web, then Sanctum SPA cookie/session auth succeeds and subsequent API calls are authorised (FR-8, AD-18).
- Given the same User, when Hybrid requests a token, then Sanctum API token auth works for `/api/v1`.
- Given failed login (unknown or wrong password), when attempted, then response does not reveal email existence and an Audit Event is recorded (FR-10).
- Given logout, when Web or Hybrid logout/revoke runs, then session ends or token is invalidated as designed.
- Given the public API surface, when probed for registration, then no public self-registration exists (FR-5).
- Given verification commands, when run, then all matrix and regression tests pass.

## Design Notes

Prefer login/token failure body `{ message: "These credentials do not match our records.", code: "authentication_failed" }` (UK English OK to keep Laravel’s familiar phrasing or equivalent). Web login: `POST /api/v1/login` after `GET /sanctum/csrf-cookie` with `credentials: 'include'`. Hybrid: `POST /api/v1/token` returns `{ token, token_type: "Bearer" }`; revoke via `POST /api/v1/logout` when Bearer-authenticated (delete current token) and session logout when cookie-authenticated. Audit rows: at minimum `event_type`, `tenant_id?`, `user_id?`, `ip`, `user_agent`, `created_at` — append-only (no update/delete API). Do not implement Role assignment or shell chrome here.

## Verification

**Commands:**
- `php artisan test --filter=Authentication` -- expected: auth matrix tests pass
- `php artisan test --filter=Tenant` -- expected: tenancy still green
- `php artisan test --filter=FeatureFlag` -- expected: flags still green
- `php artisan test --filter=Health` -- expected: health still green
- `npm test` -- expected: LoginPage (+ prior) tests pass

**Manual checks (if no CLI):**
- No Inertia; no public `/register`; Web uses cookies, Hybrid uses Bearer tokens.

## Suggested Review Order

**Auth API (entry)**

- Session login, Hybrid token issue, logout/revoke with anonymous failed audits
  [`AuthController.php:26`](../../app/Http/Controllers/Api/V1/AuthController.php#L26)

- Public login/token; protected routes under `auth:sanctum`
  [`api.php:19`](../../routes/api.php#L19)

**Sanctum wiring**

- Stateful SPA API middleware for cookie sessions
  [`app.php:17`](../../bootstrap/app.php#L17)

- User gains API token capability
  [`User.php:20`](../../app/Models/User.php#L20)

**Audit**

- Append-only auth event writer (success/fail/logout)
  [`AuditWriter.php:14`](../../app/Domain/Audit/AuditWriter.php#L14)

**Vue SPA**

- Credentialed CSRF + login helper
  [`auth.js:35`](../../resources/js/api/auth.js#L35)

- Sign-in page with generic error copy
  [`LoginPage.vue:1`](../../resources/js/pages/LoginPage.vue#L1)

- `/login` route registered; no public register
  [`index.js:15`](../../resources/js/router/index.js#L15)

**Tests**

- Matrix coverage including throttle, audit fields, and revoke
  [`AuthenticationTest.php:20`](../../tests/Feature/AuthenticationTest.php#L20)
