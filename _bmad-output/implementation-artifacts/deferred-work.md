- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-root-laravel-scaffold-and-design-tokens.md`
  summary: Load Inter via package or self-host instead of system fallback only.
  evidence: DESIGN.md prefers Inter; scaffold may declare the stack without shipping font files.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-root-laravel-scaffold-and-design-tokens.md`
  summary: Replace SVG text wordmark with outlined path geometry for consistent cross-OS brand rendering.
  evidence: Segoe Script / Bradley Hand are often missing on some OSes.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-2-tenant-school-and-isolation.md`
  summary: Document/platform-operator withoutGlobalScopes escape hatch for cross-Tenant ops.
  evidence: AD-2 allows explicit platform-operator context; Story 1.2 scopes all School queries to current Tenant with no documented bypass.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-2-tenant-school-and-isolation.md`
  summary: Apply BelongsToTenant (or equivalent) to User queries when User admin APIs land.
  evidence: Review noted User has tenant_id but is not globally scoped; Story 1.5 will expose User endpoints.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-feature-flags-by-configuration.md`
  summary: Add optional School-level feature flag overrides on top of Tenant defaults.
  evidence: Story 1.3 shipped Tenant-only flags; matrix allowed School override as optional and it was deferred.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-4-authentication-for-web-and-hybrid.md`
  summary: Add SPA route guards and authenticated /me (or equivalent) session bootstrap for the app shell.
  evidence: Review noted / and /login lack auth navigation guards and login returns no user payload; Story 1.8 owns authenticated shell IA.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-4-authentication-for-web-and-hybrid.md`
  summary: Decide Hybrid Sanctum token expiry and same-device token rotation/caps.
  evidence: Review noted expiration null and unbounded token accumulation; spec Ask First deferred product lifetime policy.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-4-authentication-for-web-and-hybrid.md`
  summary: Enforce AuditEvent append-only at model/DB layer beyond write-only API convention.
  evidence: Review noted AuditEvent is documented append-only without update/delete guards; Story 1.7 expands Audit.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5-roles-policies-and-user-administration.md`
  summary: Tenant Admin User administration API — invite/create, list, Role/School-scope update, password reset, deactivate, and last-Tenant-Admin orphan guard (FR-54).
  evidence: Split from Story 1.5 to keep Roles/policies/deactivation/AccessDenied within token budget; User CRUD is independently shippable after Role column exists.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5-roles-policies-and-user-administration.md`
  summary: Wire SPA 403/forbidden responses to `/access-denied` (AccessDenied page) and add navigation CTA.
  evidence: Review noted AccessDenied route exists but no API client/router maps 403 into it; belongs with shell (1.8) or User-admin UX.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5-roles-policies-and-user-administration.md`
  summary: Enforce school_user pivot in authorization (view only schools in scope) and prevent cross-tenant pivot attaches.
  evidence: Review noted pivot unused by policies and no shared-tenant check on attach; meaningful once User admin assigns School scope.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5b-user-administration.md`
  summary: Add reactivate (clear deactivated_at) API for Tenant Admin after deactivate.
  evidence: Review noted deactivate-only surface with no recovery path.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5b-user-administration.md`
  summary: Paginate/filter Tenant Admin User list for large Tenants.
  evidence: Review noted index uses get() with no pagination.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-sso-readiness-without-rebuilding-tenancy.md`
  summary: Add required metadata validation when sso_enabled is true, and/or live IdP protocol choice (SAML vs OIDC).
  evidence: Review noted enable-without-provider is allowed for Pilot stubs; Architecture open Q remains for first Trust enablement.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-sso-readiness-without-rebuilding-tenancy.md`
  summary: Harden LinkExternalIdByEmail with transaction/row lock against concurrent unique races.
  evidence: Review noted check-then-write can surface raw DB unique violations under concurrency.
