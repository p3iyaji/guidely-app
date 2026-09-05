# Epic 1 Context: Secure Tenant Workspace & Staff Access

<!-- Compiled from planning artifacts. Edit freely. Regenerate with compile-epic-context if planning docs change. -->

## Goal

Staff and admins can stand up an isolated Tenant, provision Users and Roles, sign in (Web session / Hybrid token), and operate under feature flags with append-only audit — including Pilot toolkit and repeatable School onboarding hooks. This epic is the foundation for all later product surfaces: tenancy, identity, auth, chrome, and compliance logging must land before Pupils, Evidence, or SRE work.

## Stories

- Story 1.1: Root Laravel scaffold and design tokens
- Story 1.2: Tenant, School, and isolation
- Story 1.3: Feature flags by configuration
- Story 1.4: Authentication for Web and Hybrid
- Story 1.5: Roles, policies, and User administration
- Story 1.6: SSO readiness without rebuilding tenancy
- Story 1.7: Append-only Audit Events
- Story 1.8: Authenticated app shell by Role
- Story 1.9: Pilot toolkit and School onboarding tooling

## Requirements & Constraints

**Tenancy (FR-1–FR-4):** Tenant data is logically isolated. A Trust Tenant can attach multiple Schools. Tenant Admin can activate a subset of Schools or a named cohort before Trust-wide rollout. Trust Dashboard, Connector types, and advanced Documentation Output packs are gated by Tenant/School configuration — not custom code forks. Disabled flags return explicit not-available responses (never empty “perfect” aggregates).

**Identity & access (FR-5–FR-10, FR-54):** No public self-signup. Tenant Admin (or Pilot operator) provisions Users with Roles and School/Trust scope. Roles: Teacher, Support Staff, SENCO, School Leader, Tenant Admin; Trust SEND Lead and Trust Executive when Trust features are on; Platform Operator is a separate GuidelyEdu identity. Users see only Pupils/fields in their scope. Auth is GuidelyEdu-managed credentials (password/session); Hybrid uses token auth. SSO readiness (external-id + enablement path) without rebuilding tenancy/Roles. Auth, access, mutations, generation, and exports emit Audit Events. Tenant Admin invites/deactivates Users, changes Roles, resets access; last Tenant Admin cannot orphan the Tenant. Deactivation immediately blocks API access.

**Pilot & onboarding (FR-55, FR-57):** Pilot Tenant bootstrap with sample-or-empty cohort, Import Template placeholder, disclaimer pack, and success-metrics export stub. New School onboarding follows a repeatable config/runbook path (no founder-only code edits).

**NFRs:** UK English product language; User-facing dates Europe/London, stored UTC. UK GDPR/DPA 2018 — minimisation, purpose limitation, Tenant-controlled retention (no invented numeric default), encryption in transit and at rest. UK or UK-adequate residency for primary DB, object storage, and backups. Audit Events append-only; no product UI purge. Failed SRE/sync/5xx visible to operators within 5 minutes (observability baseline). Web WCAG 2.2 AA for staff flows (shell/a11y baseline this epic).

**Permission matrix:** Normative source is PRD addendum — enforced on the API for every mutating and sensitive read. Vue Role menus are UX only; hidden nav ≠ authorised. User admin is Tenant Admin; cross-Tenant Pilot ops and Ontology publish are Platform Operator.

## Technical Decisions

- **Root Laravel + Vue SPA:** Classic Laravel 13 at repository root (JSON API + workers) with Vue 3.5 SPA under `resources/js` (Vite 8, Tailwind 4). **No Inertia** — product UI is client-side Vue talking to `/api/v1/*`. Capacitor 8.5 Hybrid wraps the same SPA later. Stack pin: PHP 8.3+, Sanctum, PostgreSQL 18.x (16+ if host constrains), Redis queues (non-local), S3-compatible encrypted object storage.
- **AD-1:** Single versioned JSON API for Web and Hybrid; Vue is presentation only.
- **AD-2:** Every Tenant-owned row carries `tenant_id`; queries/policies default to current Tenant.
- **AD-3:** Laravel policies/gates enforce the addendum matrix; Domain modules include Tenancy, Identity, Audit (plus stubs for later domains).
- **AD-7:** Audit Events append-only; no update/delete API in product UI.
- **AD-8:** Feature flags gate exposure in DB config, not codebase existence.
- **AD-12:** Dependency direction Presentation → Application → Domain → Infrastructure.
- **AD-14:** UK-first residency for deploy targets.
- **AD-18:** Web = Sanctum SPA cookie/session; Hybrid = Sanctum API tokens; same User/Role model. SSO populates `external_id` and may issue either after IdP callback (protocol SAML vs OIDC remains an open question for first Trust enablement).
- **Conventions:** ULID/UUID in API; errors `{ message, code?, errors? }` (403 authz, 422 validation); correlate `tenant_id` / `user_id` / `request_id` in logs; never log full Evidence bodies at info.
- **Out of this epic’s product IA:** parent portal, student LMS, clinician EMR, LA ops, marketing site, lesson planning, attendance/budget MIS.

## UX & Interaction Patterns

- **Tokens (light mode only):** Canvas `#F0F0F5`, surface white, primary/topbar periwinkle `#5B63E6`, secondary emerald `#10B981`, soft status pairs, focus ring. Inter (or Inter-like) UI type; brand script/SVG wordmark in top bar only. Page padding ~24px, sidebar ~240px, topbar ~56px, card radius ~12px.
- **Authenticated shell (≥1024):** Full-width purple top bar (logo, role nav, scoped search stub, avatar) + white left sidebar + canvas main with card grids. 768–1023: collapsible/icon sidebar. &lt;768 Teacher/Support: bottom nav Home, Pupils, Capture, Drafts, More; SENCO hamburger/sheet.
- **Role sidebar IA:** Teacher/Support — Dashboard, My Pupils, Capture, Drafts, Settings (no Messages). SENCO — Dashboard, Pupils, Review Cycles, Gaps, Outputs, School Report, Import, Settings. School Leader — Dashboard, School Report, Review Cycles (read), Settings. Trust SEND Lead — Trust Dashboard, Schools, Alerts, Settings. Tenant Admin — Users, Schools, Connectors, Feature flags, Pilot toolkit, Settings.
- **Shared primitives to establish:** TopBar, Sidebar, Card, KpiCard, Primary/Secondary/Outline buttons, StatusPill stub, LoadingSkeletons, EmptyStates, FeatureFlaggedEmpty (“Not available for this Tenant”), focus ring. Permission denied deep links → “You don’t have access.” Danger actions (e.g. deactivate last admin) need confirm dialogs.
- **Visual do/don’t:** Subtle card shadow only; no dark mode, purple glow, neon, or gamified chrome. Sign-in is in IA; Role home may show placeholder KPI row for later epics.

## Cross-Story Dependencies

- 1.1 scaffold/tokens before API domains (1.2+) and shell (1.8).
- 1.2 tenancy before flags (1.3), auth (1.4), and User admin (1.5).
- 1.4 auth before Role policies (1.5), SSO hooks (1.6), audit emitters (1.7), and shell (1.8).
- 1.5 Roles before Role-specific shell IA (1.8) and Pilot toolkit ops (1.9).
- 1.7 audit should receive events from auth and User admin as those land; later epics extend emitters for Evidence/Outputs/Overrides.
- 1.9 Pilot/onboarding tooling assumes Tenants, Schools, flags, and Users already exist.
