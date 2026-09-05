---
stepsCompleted: ['step-01-validate-prerequisites', 'step-02-design-epics', 'step-03-create-stories', 'step-04-final-validation']
inputDocuments:
  - prds/prd-guidely-app-2026-08-16/prd.md
  - prds/prd-guidely-app-2026-08-16/addendum.md
  - architecture/architecture-guidely-app-2026-08-20/ARCHITECTURE-SPINE.md
  - ux-designs/ux-guidely-app-2026-08-20/DESIGN.md
  - ux-designs/ux-guidely-app-2026-08-20/EXPERIENCE.md
---

# guidely-app - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for guidely-app, decomposing the requirements from the PRD, UX Design if it exists, and Architecture requirements into implementable stories.

## Requirements Inventory

### Functional Requirements

FR-1: Tenant isolation — Tenant Admin and platform operators work within a Tenant whose Pupil, Evidence Record and User data are logically separated from every other Tenant.
FR-2: Trust contains Schools — A Trust Tenant can attach multiple Schools, each with its own Users, Pupils and Evidence Bases.
FR-3: Incremental School activation — Tenant Admin can enable GuidelyEdu for a subset of Schools or a named Pupil cohort before Trust-wide rollout.
FR-4: Feature flags by configuration — Trust Dashboard, Connector types and advanced Documentation Output packs are enabled per Tenant/School by configuration, not custom code.
FR-5: Provisioned accounts — Tenant Admin (or implementation operator in Pilot) creates Users and assigns Roles and School/Trust scope. No public self-signup.
FR-6: Role set — Tenant Roles: Teacher, Support Staff, SENCO, School Leader, Tenant Admin; Trust SEND Lead and Trust Executive when Trust features on; Platform Operator is a separate GuidelyEdu identity.
FR-7: Least privilege on Pupils — Users access only Pupils in their scoped Schools, and only fields required for their Role.
FR-8: Authentication — Users authenticate with credentials managed by GuidelyEdu (password/session); Hybrid uses token auth per Architecture AD-18.
FR-9: SSO readiness — Identity architecture allows connection to School/Trust SSO without rebuilding tenancy or Roles.
FR-10: Access logging — Authentication, record access, modifications, Documentation Output generation and exports emit Audit Events.
FR-11: Pupil records — SENCO and Tenant Admin can create and maintain Pupil working records (identity keys, School, year group, SEND status).
FR-12: Need on the Pupil — SENCO can record primary and secondary Need categories from the Ontology Need taxonomy.
FR-13: Cohort and class assignment — Tenant Admin or SENCO can assign Pupils to classes/cohorts so Teachers see the right list (default: SENCO assignment; MIS class when Connector present).
FR-14: Record Observation — Teacher and Support Staff can submit an Observation against a Pupil with required structured fields.
FR-15: Record Intervention — Teacher, Support Staff and SENCO can record an Intervention linked to a Provision taxonomy term and the Pupil.
FR-16: Record Pupil Response — Users who can record Interventions can record Pupil Response linked to an Intervention or dated context.
FR-17: No parallel-logging mandate — Product does not require retyping an event that arrived via Connector with equivalent mapped fields.
FR-18: Draft Evidence Record — User can save a draft Observation/Intervention/Pupil Response and submit later; drafts never enter SRE.
FR-19: Hybrid-appropriate capture — Hybrid Client supports FR-14–FR-16 with the same server validations as Web Client; offline queue with not-on-server labelling.
FR-20: Chronological Evidence Base — SENCO and authorised Roles can view a Pupil’s Evidence Records in time order, filterable by type.
FR-21: Amend with history — Authors and SENCO can correct an Evidence Record; previous version remains retrievable.
FR-22: Review notes — SENCO can add a review note Evidence Record marked as professional commentary.
FR-23: Five Ontology domains — Operational Ontology exposes Need, Provision, Outcome framework, Threshold Definitions and Relationship Mappings.
FR-24: Map evidence into the Ontology — On submit/import, each Evidence Record is mapped to Ontology terms required for Rules.
FR-25: Versioned Ontology — Ontology changes are versioned; historical Determinations retain the version used.
FR-26: Four evaluation dimensions — Every SRE run produces Determinations across Sequential Compliance, Evidential Sufficiency, Proportionality and Outcome Progression (or explicit no applicable Rule).
FR-27: Rule shape — Each Rule has defined condition, evaluation, and Determination outcome, and belongs to a Rule Library version.
FR-28: Re-evaluate on new evidence — Creating, amending or importing Evidence Records re-runs applicable Rules for that Pupil (async job).
FR-29: Determinism — Same Evidence Base + Rule Library version + Ontology version always yields the same Determinations; no LLM on this path.
FR-30: Subset Rule coverage (initial library) — First build ships an initial Rule set for common documentation and threshold scenarios, not full SEND framework coverage claim.
FR-31: Rule expansion for escalation and review thresholds — Rule Library includes escalation and review-threshold Rules without changing FR-26–FR-29.
FR-32: Reasoning Pathway on every Determination — SENCO (and permitted Roles) can open the Reasoning Pathway for any current or historical Determination.
FR-33: Human confirmation on Documentation Output — Generating a Documentation Output requires explicit confirmation that a named User accepts it as professionally reviewed.
FR-34: Override — SENCO or School Leader can Override a failing/insufficient Determination with mandatory rationale (≥20 chars); does not delete Determination.
FR-35: No diagnostic claims — Product copy, exports and APIs do not label Determinations as diagnoses, eligibility decisions, or predicted outcomes.
FR-36: Gap list per Pupil — SENCO can view open Gaps for a Pupil derived from current Determinations (SRE-owned; not hand-edited).
FR-37: Documentation status — Pupil Documentation Status is exactly `ready` | `gaps` | `uncovered` | `not-started` | `evaluating`.
FR-38: Review summary — SENCO can generate a review summary Documentation Output for a Pupil and Review Cycle.
FR-39: EHCP evidence pack — SENCO can generate an EHCP evidence pack Documentation Output from the Evidence Base.
FR-40: Export-ready file — Documentation Outputs download as portable file (PDF or equivalent) plus structured sidecar for audit; encrypted object storage.
FR-41: Tribunal and inspection packs — Documentation Output types for tribunal and inspection contexts (Tenant flag), with advanced Audit Event trace.
FR-42: Create Review Cycle — SENCO can create a Review Cycle for a Pupil with type (including Annual Review), due date, and optional EHCP link flag.
FR-43: Due list — SENCO and School Leader can list Review Cycles due in a date window for their School.
FR-44: Automated Review Cycle management — Product can create or roll forward Review Cycles from Tenant policy when configured (default: manual close).
FR-45: School documentation report — School Leader and SENCO can open a School Report: counts by Documentation Status, open Gaps, Review Cycles due; no attendance/budget KPIs.
FR-46: School Report performance bound — FR-45 report generates in under 5 seconds for ≤500 Pupils.
FR-47: Import Template — Tenant Admin or SENCO can upload Import Template for Pupils and optional historical Evidence Records; partial success with row errors.
FR-48: Connector configuration — Tenant Admin can enable a Connector with School-controlled field sharing; secrets not displayed back.
FR-49: Sync without duplication — Connector payloads upsert by MIS key.
FR-50: Standardised MIS Connectors — Production connectors with repeatable onboarding; Pilot freezes one primary adapter; Import Template always available.
FR-51: Multi-academy Indicators — Trust SEND Lead can view Indicators across enabled Schools (lateness, Gap density, status mix); default no Pupil narrative dump.
FR-52: Escalation Indicators — Trust Dashboard can flag Schools or Pupils meeting encoded escalation Rules with explainable Rule citation.
FR-53: Portfolio benchmarking and trends — Cross-School benchmarking/trends when flagged; aggregate only; not required for Pilot SM validation.
FR-54: User and Role administration — Tenant Admin can invite/deactivate Users, change Roles, reset access; last Tenant Admin cannot orphan Tenant.
FR-55: Pilot toolkit — Pilot Tenant creation with cohort/template/disclaimer pack and success-metrics export.
FR-56: Rule and Ontology administration (operator) — Platform Operator can load a new Ontology/Rule Library version into a Tenant via controlled publish.
FR-57: Standardised deployment tooling — New School onboarding in a Trust follows a repeatable runbook/config path rather than founder-only setup.
FR-58: Safeguarding signal ingest — Minimised safeguarding signal ingest when Tenant flag on (default off); not case notes; SENCO + School Leader only; not required for Pilot SMs.
FR-59: Compliance risk alerts — Automated alerts when documentation risk Indicators cross thresholds at School or Trust level (flagged).
FR-60: Controlled Ontology roll-forward — Platform can publish a new Ontology version to Tenants with migration that preserves historical Determinations.

### NonFunctional Requirements

NFR-1: Latency (capture) — Submitting an Observation from Web or Hybrid Client returns success or validation error within 2 seconds under Pilot load (≤100 concurrent Users per Tenant).
NFR-2: Latency (SRE) — SRE run for one Pupil with ≤500 Evidence Records completes within 3 seconds.
NFR-3: Availability — Pilot target 99.0% monthly excluding planned windows; paid Trust Tenants 99.5%.
NFR-4: Accessibility — Web Client WCAG 2.2 AA for SENCO and Teacher flows.
NFR-5: Observability — Failed SRE runs, sync failures, and 5xx rates are visible to platform operators within 5 minutes.
NFR-6: Localization — Product language English (UK); US statutory terms (e.g. IEP as primary object) are defects.
NFR-7: Time — All User-facing dates in Europe/London; stored in UTC.

Additional product quality / compliance constraints from PRD (treat as NFR-adjacent for stories):
NFR-8: UK GDPR / DPA 2018 — minimisation, purpose limitation, Tenant-controlled retention (no product numeric default until legal configures), encryption in transit and at rest.
NFR-9: Data residency — UK or explicitly UK-adequate regions for primary DB, object storage, and backups (Architecture AD-14).
NFR-10: Audit immutability — Audit Events append-only; no product UI purge.
NFR-11: Deterministic SRE path — No generative/LLM calls on Determination or Reasoning Pathway generation.

### Additional Requirements

- **Starter / structural seed:** Classic **root Laravel 13** app (JSON API + workers) with Vue 3.5 SPA under `resources/js` (Vite 8 + Tailwind 4). **No Inertia** — SPA consumes `/api/v1/*` JSON only; Blade is SPA shell only. Capacitor wraps the same SPA later. Epic 1 Story 1 must scaffold this layout.
- **Stack pin:** PHP 8.3+, Laravel 13.x, Sanctum, PostgreSQL 18.x (16+ if host constrains), Vue 3.5.x, Vite 8.x, Tailwind 4.x, Redis queues (non-local), S3-compatible encrypted object storage, Protégé off-platform → import command.
- **AD-1:** Single versioned JSON API for Web and Hybrid; Vue is presentation only.
- **AD-2:** Every Tenant-owned row carries `tenant_id`; queries/policies default to current Tenant.
- **AD-3:** Laravel policies/gates enforce addendum permission matrix on every mutating and sensitive read endpoint.
- **AD-4:** SRE is in-process pure evaluator; no LLM on Determination path.
- **AD-5:** Ontology terms and Rules are versioned data; Determinations store versions used.
- **AD-6 / AD-17:** Evidence commit sync; SRE via job `SreReevaluatePupil(tenant_id, pupil_id, reason)` with pinned Ontology + Rule Library versions.
- **AD-7:** Audit Events append-only.
- **AD-8:** Feature flags gate exposure, not codebase existence; disabled → explicit not-available.
- **AD-9:** Hybrid offline drafts client-local until API accepts; server wins Pupil identity conflicts.
- **AD-10:** Documentation Outputs require confirmation payload; files in encrypted object storage; DB metadata + checksum.
- **AD-11:** Import Template required path; Connectors optional swappable adapters behind one interface (OQ-4 picks Pilot adapter).
- **AD-12:** Dependency direction Presentation → Application → Domain → Infrastructure; SRE never imports HTTP/UI.
- **AD-13:** Implement DESIGN.md tokens and EXPERIENCE.md IA; wireframe PDF visual-only.
- **AD-14:** UK-first residency.
- **AD-15:** Module ownership — Evidence owns Evidence mutations; Sre owns Determinations/Gaps/Overrides; Outputs and Reporting are read-only on those entities.
- **AD-16:** Evidence lifecycle `draft` | `submitted`; Documentation Status enum includes `evaluating`.
- **AD-18:** Web = Sanctum SPA cookie/session; Hybrid = Sanctum API tokens; same User/Role model.
- **AD-19:** Canonical Laravel API Resources for Pupil, EvidenceRecord, Determination, Gap, ReviewCycle, DocumentationOutput, SchoolReport, TrustIndicator.
- **Domain modules:** Tenancy, Identity, Pupils, Evidence, Ontology, Sre, Outputs, Reporting, Connectors, Audit, Reviews.
- **IDs:** ULID/UUID in API; never sequential Pupil ids exposed cross-Tenant.
- **Errors:** JSON `{ message, code?, errors? }`; 403 authz; 422 validation.
- **Environments:** dev / staging / prod UK with API + workers, PostgreSQL, object storage, Redis.
- **Permission matrix:** Normative source PRD addendum §3 including Platform Operator column.
- **Deferred for stories as non-blockers:** exact cloud vendor, first MIS brand (gate), OpenAPI codegen, multi-region, retention numeric defaults, full platform-operator UI (artisan + minimal admin OK first).

### UX Design Requirements

UX-DR1: Implement DESIGN.md colour tokens (canvas, surface, primary periwinkle `#5B63E6`, secondary emerald `#10B981`, status soft pairs, topbar, focus-ring) as Tailwind/CSS variables; light mode only in v1.
UX-DR2: Implement typography tokens — Inter (or Inter-like) for UI; brand script/SVG wordmark in top bar only; metric style for KPI numbers; UK English / PRD Glossary terms in all chrome.
UX-DR3: Implement spacing/radius tokens (page 24px, sidebar 240px, topbar 56px, card radius ~12px, status pills full).
UX-DR4: Authenticated app shell — full-width purple top bar (logo, role nav, scoped search, avatar) + white left sidebar + canvas main with card grids (≥1024px).
UX-DR5: Responsive shells — 768–1023 collapsible/icon sidebar; &lt;768 Hybrid Teacher/Support Staff bottom nav: Home, Pupils, Capture, Drafts, More; SENCO hamburger/sheet; single-column capture-first.
UX-DR6: Shared UI components: TopBar, Sidebar, Card, KpiCard, PrimaryButton, SecondaryButton (emerald CTA only), OutlineQuickAction, StatusPill, ListRow, ProgressBar, ReasoningPathwayPanel, DisclaimerBanner, ConfirmOutputDialog, OverrideDialog, ImportResultsTable, FeatureFlaggedEmpty, LoadingSkeletons, EmptyStates.
UX-DR7: StatusPill values exactly `ready` | `gaps` | `uncovered` | `not-started` | `evaluating` (PRD/Architecture lock; never colour-only — text + icon).
UX-DR8: Role-specific sidebar IA per EXPERIENCE.md (Teacher/Support, SENCO, School Leader, Trust SEND Lead, Tenant Admin); omit in-app messaging.
UX-DR9: Surface map — implement routes/screens for Sign-in, Role home, My Pupils, Capture Observation/Intervention/Pupil Response, Drafts, Evidence Base, Determinations & Gaps, Reasoning Pathway, Override, Review Cycles, Documentation Outputs, School Report, Import Template, Connectors, Trust Dashboard, Users & Roles, Pilot toolkit, Ontology/Rule publish (operator), Compliance alerts, Safeguarding signal context (flagged).
UX-DR10: Out of IA — do not build parent portal, student LMS, clinician EMR, LA ops, marketing site, lesson planning, attendance/budget MIS, EduConnect/wireframe product IA.
UX-DR11: Voice/tone — documentation language only; FR-35 disclaimer always near Determinations and Outputs; ban confidence %, diagnosis copy, “AI found risks”.
UX-DR12: Capture form pattern — Ontology-bound required fields; validate on submit; success returns to Evidence Base with new row highlighted; Hybrid tap targets ≥44px; Pupil picker bottom sheet on mobile.
UX-DR13: Gap list links to Reasoning Pathway; Gaps closed only by meeting Rule or Override; Reasoning Pathway expandable Rule id/name/version → conditions → Evidence links → Determination.
UX-DR14: Confirm output dialog — checkbox + named User; blocked without confirmation (FR-33).
UX-DR15: Override dialog — mandatory rationale ≥20 characters; keeps Determination history.
UX-DR16: Import results — partial success table (committed vs row errors).
UX-DR17: Feature-flagged empty — “Not available for this Tenant”; never empty chart implying zero risk.
UX-DR18: State patterns — loading skeletons; empty Pupils/Evidence CTAs; Uncovered labelled not success; offline Hybrid draft banner; sync conflict server-wins messaging; permission denied deep-link page; SRE evaluating spinner on Pupil status (no full-page block unless &gt;3s); danger-action confirm dialogs.
UX-DR19: Accessibility floor — WCAG 2.2 AA; visible focus ring; Tab = reading order; screen reader surface announcements; form labels + `aria-describedby` errors; `prefers-reduced-motion` for skeletons; Reasoning Pathway readable without colour; heading hierarchy h1→h3.
UX-DR20: Top bar search scoped to Pupils and Review Cycles within Role scope — not free-text Trust-wide for Teachers.
UX-DR21: School Leader — School Report primary; Reasoning Pathway read-only on drill-down; no Evidence Record edit.
UX-DR22: Key flow fidelity — UJ-1 Teacher Hybrid capture; UJ-2 SENCO Annual Review path; UJ-3 School Report readiness; UJ-4 Import before Connector; UJ-5 Trust Indicators drill to School Report.
UX-DR23: Visual do/don’t — subtle card shadow only; no dark mode, purple glow, neon, gamified Determinations, or secondary green used as Gap/success meaning.
UX-DR24: Align key screens to promoted mocks (`teacher-capture.html`, `senco-evidence-base.html`, `school-report.html`) where they do not conflict with spines.

### FR Coverage Map

FR-1: Epic 1 — Tenant isolation
FR-2: Epic 1 — Trust contains Schools
FR-3: Epic 1 — Incremental School activation
FR-4: Epic 1 — Feature flags by configuration
FR-5: Epic 1 — Provisioned accounts
FR-6: Epic 1 — Role set
FR-7: Epic 1 — Least privilege on Pupils
FR-8: Epic 1 — Authentication
FR-9: Epic 1 — SSO readiness
FR-10: Epic 1 — Access logging / Audit Events
FR-11: Epic 2 — Pupil records
FR-12: Epic 2 — Need on the Pupil
FR-13: Epic 2 — Cohort and class assignment
FR-14: Epic 3 — Record Observation
FR-15: Epic 3 — Record Intervention
FR-16: Epic 3 — Record Pupil Response
FR-17: Epic 3 — No parallel-logging mandate
FR-18: Epic 3 — Draft Evidence Record
FR-19: Epic 3 — Hybrid-appropriate capture
FR-20: Epic 4 — Chronological Evidence Base
FR-21: Epic 4 — Amend with history
FR-22: Epic 4 — Review notes
FR-23: Epic 4 — Five Ontology domains
FR-24: Epic 4 — Map evidence into the Ontology
FR-25: Epic 4 — Versioned Ontology
FR-26: Epic 4 — Four evaluation dimensions
FR-27: Epic 4 — Rule shape
FR-28: Epic 4 — Re-evaluate on new evidence
FR-29: Epic 4 — Determinism
FR-30: Epic 4 — Subset Rule coverage
FR-31: Epic 4 — Escalation and review-threshold Rules
FR-32: Epic 4 — Reasoning Pathway
FR-33: Epic 5 — Human confirmation on Documentation Output
FR-34: Epic 4 — Override
FR-35: Epic 4 — No diagnostic claims
FR-36: Epic 4 — Gap list per Pupil
FR-37: Epic 4 — Documentation status
FR-38: Epic 5 — Review summary
FR-39: Epic 5 — EHCP evidence pack
FR-40: Epic 5 — Export-ready file
FR-41: Epic 5 — Tribunal and inspection packs
FR-42: Epic 5 — Create Review Cycle
FR-43: Epic 5 — Due list
FR-44: Epic 5 — Automated Review Cycle management
FR-45: Epic 5 — School documentation report
FR-46: Epic 5 — School Report performance bound
FR-47: Epic 2 — Import Template
FR-48: Epic 6 — Connector configuration
FR-49: Epic 6 — Sync without duplication
FR-50: Epic 6 — Standardised MIS Connectors
FR-51: Epic 7 — Multi-academy Indicators
FR-52: Epic 7 — Escalation Indicators
FR-53: Epic 7 — Portfolio benchmarking and trends
FR-54: Epic 1 — User and Role administration
FR-55: Epic 1 — Pilot toolkit
FR-56: Epic 4 — Rule and Ontology administration
FR-57: Epic 1 — Standardised deployment tooling
FR-58: Epic 7 — Safeguarding signal ingest
FR-59: Epic 7 — Compliance risk alerts
FR-60: Epic 4 — Controlled Ontology roll-forward

## Epic List

### Epic 1: Secure Tenant Workspace & Staff Access
Staff and admins can stand up an isolated Tenant, provision Users/Roles, sign in (Web session / Hybrid token), and operate under feature flags with append-only audit — including Pilot toolkit and repeatable School onboarding hooks.
**FRs covered:** FR-1, FR-2, FR-3, FR-4, FR-5, FR-6, FR-7, FR-8, FR-9, FR-10, FR-54, FR-55, FR-57

### Epic 2: Pupil Cohort & Import Onboarding
SENCO/Admin can maintain Pupils, Needs, and Teacher assignments, and load a cohort via Import Template so capture can start without a live MIS Connector.
**FRs covered:** FR-11, FR-12, FR-13, FR-47

### Epic 3: Structured Evidence Capture (Web + Hybrid)
Teachers, Support Staff, and SENCO can capture Observation / Intervention / Pupil Response with drafts, Ontology-bound fields, and Hybrid offline queue — same API validations as Web.
**FRs covered:** FR-14, FR-15, FR-16, FR-17, FR-18, FR-19

### Epic 4: Evidence Base & Statutory Reasoning
Authorised staff can work a chronological Evidence Base; Platform Operator publishes versioned Ontology/Rules; SRE produces deterministic Determinations, Gaps, Documentation Status, Reasoning Pathways, and Overrides — never diagnoses.
**FRs covered:** FR-20, FR-21, FR-22, FR-23, FR-24, FR-25, FR-26, FR-27, FR-28, FR-29, FR-30, FR-31, FR-32, FR-34, FR-35, FR-36, FR-37, FR-56, FR-60

### Epic 5: Review Cycles, Documentation Outputs & School Report
SENCO runs Review Cycles and generates confirmed Documentation Outputs; School Leader/SENCO see School Report readiness within the performance bound.
**FRs covered:** FR-33, FR-38, FR-39, FR-40, FR-41, FR-42, FR-43, FR-44, FR-45, FR-46

### Epic 6: MIS Connectors
Tenant Admin can configure a Connector with field sharing, sync without duplication, and enable a Pilot-chosen standardised adapter — Import Template remains the fallback.
**FRs covered:** FR-48, FR-49, FR-50

### Epic 7: Trust Oversight & Advanced Signals
Trust roles see multi-School Indicators, escalations, and (when flagged) benchmarking/trends, compliance alerts, and minimised safeguarding context.
**FRs covered:** FR-51, FR-52, FR-53, FR-58, FR-59

## Epic 1: Secure Tenant Workspace & Staff Access

Staff and admins can stand up an isolated Tenant, provision Users/Roles, sign in (Web session / Hybrid token), and operate under feature flags with append-only audit — including Pilot toolkit and repeatable School onboarding hooks.

**FRs:** FR-1–FR-10, FR-54, FR-55, FR-57  
**NFRs / Arch:** NFR-5–7, NFR-8–9, AD-1–3, AD-7–8, AD-12, AD-14, AD-18–19; root Laravel + Vue SPA scaffold  
**UX-DRs:** UX-DR1–UX-DR6, UX-DR8, UX-DR10, UX-DR17, UX-DR19 (shell/a11y baseline), UX-DR23

### Story 1.1: Root Laravel scaffold and design tokens

As a Platform Operator,
I want a runnable root Laravel API and Vue SPA (no Inertia) with GuidelyEdu design tokens,
So that subsequent features share one API contract and a consistent visual system.

**Acceptance Criteria:**

**Given** an empty or non-product repo state  
**When** the scaffold is created per Architecture Structural Seed  
**Then** the project root runs Laravel 13 (PHP 8.3+) with a versioned JSON health endpoint and `resources/js` runs Vue 3.5 + Vite 8 + Tailwind 4 as a client-side SPA  
**And** Inertia is not installed or used for product UI  
**And** DESIGN.md colour, typography, spacing, and radius tokens are available as CSS/Tailwind variables (light mode only) (UX-DR1, UX-DR2, UX-DR3)  
**And** no wireframe parent/LMS/clinician IA routes exist  
**And** README documents how to run the API, Vite frontend, and queue worker locally

### Story 1.2: Tenant, School, and isolation

As a Tenant Admin,
I want Tenants and Schools modelled with mandatory Tenant isolation,
So that one School’s Pupils and Users cannot be accessed by another Tenant.

**Acceptance Criteria:**

**Given** two Tenants each with at least one School  
**When** a User authenticated in Tenant A requests Tenant B resources by id  
**Then** the API returns 403/404 without leaking existence across Tenants (FR-1)  
**And** a Trust Tenant can attach multiple Schools (FR-2)  
**And** Tenant Admin can activate a subset of Schools or a named cohort flag for incremental rollout (FR-3)  
**And** every Tenant-owned row stores `tenant_id` and policies default-scope to the current Tenant (AD-2)

### Story 1.3: Feature flags by configuration

As a Tenant Admin,
I want to enable or disable Trust Dashboard, Connectors, and advanced packs by configuration,
So that Pilot Tenants see School-only surfaces without custom code forks.

**Acceptance Criteria:**

**Given** a Tenant with Trust Dashboard flag off  
**When** a User calls a Trust Dashboard or flagged advanced endpoint  
**Then** the API returns an explicit not-available response (not empty “perfect” aggregates) (FR-4, AD-8, UX-DR17)  
**And** flags are stored per Tenant/School in DB configuration, not hardcoded per deploy  
**And** Vue shows the EXPERIENCE “Not available for this Tenant” pattern for flagged-off routes

### Story 1.4: Authentication for Web and Hybrid

As a staff User,
I want to sign in on Web with a session and on Hybrid with an API token,
So that I can securely access my Tenant from browser or device.

**Acceptance Criteria:**

**Given** a provisioned User with valid credentials  
**When** they sign in via the Web Client  
**Then** Sanctum SPA cookie/session auth succeeds and subsequent API calls are authorised (FR-8, AD-18)  
**And** Hybrid Client can obtain a Sanctum API token for the same User/Role model  
**And** failed logins do not reveal whether the email exists and emit Audit Events (FR-10)  
**And** logout ends the Web session / revokes or invalidates the Hybrid token path as designed  
**And** there is no public self-registration endpoint (FR-5)

### Story 1.5: Roles, policies, and User administration

As a Tenant Admin,
I want to provision Users, assign Roles and School/Trust scope, and enforce least privilege on the API,
So that staff only see Pupils and actions allowed for their Role.

**Acceptance Criteria:**

**Given** the Role set Teacher, Support Staff, SENCO, School Leader, Tenant Admin (and Trust Roles when Trust flags on) plus Platform Operator outside Tenant Roles (FR-6)  
**When** Tenant Admin invites/deactivates Users, changes Roles, or resets access (FR-54)  
**Then** API policies enforce the addendum permission matrix on mutating and sensitive reads (FR-7, AD-3)  
**And** Vue Role menus alone never grant access (hidden nav ≠ authorised)  
**And** deactivation immediately blocks API access  
**And** the last Tenant Admin cannot be deactivated if it would orphan the Tenant  
**And** deep links without permission show “You don’t have access” (UX-DR18)

### Story 1.6: SSO readiness without rebuilding tenancy

As a Tenant Admin / Platform Operator,
I want identity fields and hooks for School/Trust SSO,
So that SSO can be connected later without redesigning Tenants or Roles.

**Acceptance Criteria:**

**Given** GuidelyEdu-managed password auth works (Story 1.4)  
**When** SSO readiness is implemented (FR-9)  
**Then** Users can store `external_id` and Tenant SSO config stubs without requiring a live IdP in Pilot  
**And** enabling SSO later must match Users by email without duplicating accounts when emails match  
**And** documentation states SAML vs OIDC choice remains an Architecture open question for first Trust enablement

### Story 1.7: Append-only Audit Events

As a Platform Operator / compliance stakeholder,
I want authentication, access, and mutation events recorded append-only,
So that accountability cannot be purged from the product UI.

**Acceptance Criteria:**

**Given** authenticated product use  
**When** auth, sensitive record access, mutations, and (later) output/export/Override occur (FR-10, AD-7)  
**Then** Audit Events are written with `tenant_id`, `user_id`, action, and timestamp  
**And** there is no update/delete API for Audit Events in the product UI  
**And** Tenant Admin cannot purge Audit Events from the UI  
**And** info logs never include full Evidence bodies (Architecture logging convention)

### Story 1.8: Authenticated app shell by Role

As a staff User,
I want the GuidelyEdu chrome (top bar, sidebar/bottom nav) matching my Role,
So that I can navigate only the surfaces allowed for my work.

**Acceptance Criteria:**

**Given** an authenticated User on ≥1024px Web  
**When** the app shell loads  
**Then** purple top bar (~56px), white sidebar (~240px), and canvas + cards match DESIGN.md (UX-DR1–UX-DR4, UX-DR23)  
**And** sidebar items follow EXPERIENCE Role IA without Messages (UX-DR8, UX-DR10)  
**And** on &lt;768px Teacher/Support Staff see bottom nav Home, Pupils, Capture, Drafts, More (UX-DR5)  
**And** shared primitives exist: TopBar, Sidebar, Card, KpiCard, buttons, StatusPill stub, skeletons, focus ring (UX-DR6, UX-DR19)  
**And** Role home shows placeholder KPI row pattern ready for live counts in later epics  
**And** top bar search is scoped stub (Pupils/Review Cycles within scope) per UX-DR20

### Story 1.9: Pilot toolkit and School onboarding tooling

As a Platform Operator / Tenant Admin,
I want Pilot Tenant bootstrap and a repeatable School onboarding checklist/tooling path,
So that Pilots and Trust School adds do not require founder-only setup.

**Acceptance Criteria:**

**Given** Platform Operator privileges  
**When** they create a Pilot Tenant (FR-55)  
**Then** they can choose sample-or-empty cohort, download Import Template placeholder, see in-product disclaimer pack, and export a success-metrics stub  
**And** New School onboarding exposes a repeatable config checklist / CLI or admin steps covering Tenant/School, flags, and Users (FR-57)  
**And** completing the documented path does not require editing application code  
**And** UK residency config is documented for DB/object storage targets (NFR-9, AD-14)

## Epic 2: Pupil Cohort & Import Onboarding

SENCO/Admin can maintain Pupils, Needs, and Teacher assignments, and load a cohort via Import Template so capture can start without a live MIS Connector.

**FRs:** FR-11, FR-12, FR-13, FR-47  
**NFRs / Arch:** AD-2, AD-3, AD-11, AD-19 (Pupil resource); NFR-6–7  
**UX-DRs:** UX-DR7 (StatusPill on pupil rows), UX-DR9 (My Pupils / Import), UX-DR16, UX-DR18 (empty Pupils), UX-DR20 (search Pupils), UX-DR22 (UJ-4)

### Story 2.1: Pupil working records

As a SENCO or Tenant Admin,
I want to create and maintain Pupil working records for my School,
So that staff have a correct cohort identity for evidence and reviews.

**Acceptance Criteria:**

**Given** an authenticated SENCO or Tenant Admin in an active School  
**When** they create or update a Pupil (FR-11)  
**Then** the record stores identity keys, School, year group, and SEND status (SEN Support / EHCP / neither)  
**And** Pupil API responses use the canonical Laravel Pupil Resource (AD-19)  
**And** Teachers cannot create Pupils outside their Role permissions (addendum matrix)  
**And** soft-deleted / left Pupils are excluded from active Teacher pickers but retained per Tenant retention policy rules (no invented numeric default)  
**And** dates display Europe/London and store UTC (NFR-7)

### Story 2.2: Need categories on the Pupil

As a SENCO,
I want to record primary and secondary Need categories from the Ontology Need taxonomy,
So that Rules can evaluate Needs against evidence later.

**Acceptance Criteria:**

**Given** a Pupil in the SENCO’s School and a published Ontology Need taxonomy available to the Tenant (seeded stub acceptable if Epic 4 publish is not yet live — use versioned Need terms table)  
**When** SENCO sets primary and optional secondary Need (FR-12)  
**Then** only Ontology Need terms are accepted (free-text Need labels rejected)  
**And** changes are Audit Event–logged  
**And** Teachers can read Need on assigned Pupils only as allowed by least privilege (FR-7)

### Story 2.3: Cohort and class assignment for Teachers

As a Tenant Admin or SENCO,
I want to assign Pupils to classes/cohorts and Teachers,
So that each Teacher only sees the Pupils they should work with.

**Acceptance Criteria:**

**Given** Pupils exist in a School  
**When** Tenant Admin or SENCO assigns Pupils to classes/cohorts and/or Teachers (FR-13)  
**Then** a Teacher with assignments sees only those Pupils in My Pupils  
**And** a Teacher with no assignments sees an empty list with empty-state copy, not all School Pupils (UX-DR18)  
**And** default assignment source is SENCO assignment; when a Connector later supplies class membership, union is allowed without duplicating Pupil rows  
**And** Support Staff follow the same assigned-Pupils scope as Teachers

### Story 2.4: My Pupils list and pupil row UI

As a Teacher, Support Staff, or SENCO,
I want a My Pupils / Pupils list with documentation status and next review meta,
So that I can open the right Pupil quickly for capture or review.

**Acceptance Criteria:**

**Given** an authenticated scoped User  
**When** they open My Pupils / Pupils (UX-DR9)  
**Then** each row shows name, year, Documentation Status pill, and next Review Cycle date when known (UX-DR7; status may be `not-started` until Epic 4)  
**And** StatusPill never relies on colour alone (text + icon) (UX-DR19)  
**And** SENCO empty state offers CTA to Import Template / add Pupil (UX-DR18)  
**And** top bar search finds Pupils within Role scope only (UX-DR20)  
**And** UK Glossary terms only (no IEP / SENDCO) (NFR-6)

### Story 2.5: Import Template upload with partial success

As a Tenant Admin or SENCO,
I want to download and upload a structured Import Template for Pupils (and optional historical Evidence Records),
So that capture can start the same day without a live MIS Connector.

**Acceptance Criteria:**

**Given** Import Template download is available (FR-47, AD-11, UJ-4)  
**When** SENCO/Tenant Admin uploads a file  
**Then** valid rows commit and invalid rows report by row number without failing the whole file (partial success)  
**And** re-upload with the same Pupil key updates rather than duplicating  
**And** Import results UI shows committed vs row errors table (UX-DR16)  
**And** if all rows are invalid, nothing is committed and errors are clear  
**And** Teachers see newly committed Pupils the same day within assignment rules  
**And** optional historical Evidence rows in the template are created as `submitted` only when fully valid; drafts are not implied  
**And** Connector is not required for this path to succeed

## Epic 3: Structured Evidence Capture (Web + Hybrid)

Teachers, Support Staff, and SENCO can capture Observation / Intervention / Pupil Response with drafts, Ontology-bound fields, and Hybrid offline queue — same API validations as Web.

**FRs:** FR-14, FR-15, FR-16, FR-17, FR-18, FR-19  
**NFRs / Arch:** NFR-1, AD-1, AD-6, AD-9, AD-15 (Domain\Evidence), AD-16 (`draft` \| `submitted`), AD-19  
**UX-DRs:** UX-DR5, UX-DR9, UX-DR11–UX-DR12, UX-DR18 (offline banner), UX-DR19, UX-DR22 (UJ-1), UX-DR24 (teacher-capture mock)

### Story 3.1: Record Observation

As a Teacher or Support Staff,
I want to submit a structured Observation against an assigned Pupil,
So that classroom evidence is captured once in a review-ready format.

**Acceptance Criteria:**

**Given** an authenticated Teacher/Support Staff with at least one assigned Pupil  
**When** they submit an Observation with required fields (Pupil, datetime/session context, what was observed, setting) (FR-14)  
**Then** the API validates Ontology-bound required fields and returns success or 422 within 2 seconds under Pilot load assumptions (NFR-1)  
**And** lifecycle is `submitted`; the record appears on the Evidence Base path for authorised Roles  
**And** SENCO may also create Observations per Role matrix where allowed  
**And** Capture UI uses large tap targets (≥44px) on Hybrid and returns to Evidence Base / confirmation with the new row highlighted when that surface exists (UX-DR12)  
**And** Audit Event records create + client type (web|hybrid)

### Story 3.2: Record Intervention

As a Teacher, Support Staff, or SENCO,
I want to record an Intervention linked to a Provision taxonomy term,
So that provision is evidenced without a parallel spreadsheet (UJ-1).

**Acceptance Criteria:**

**Given** an assigned/in-scope Pupil and Provision terms available to the Tenant  
**When** the User submits an Intervention (FR-15)  
**Then** a Provision taxonomy term is required; free-text-only Provision is rejected  
**And** session context and notes fields follow the capture form pattern (UX-DR12)  
**And** success does not require the User to retype the same event into another GuidelyEdu form  
**And** Hybrid/Web use the same API contract and validations (AD-1, FR-19)  
**And** UI aligns with `mockups/teacher-capture.html` where it does not conflict with spines (UX-DR24)

### Story 3.3: Record Pupil Response

As a User who can record Interventions,
I want to record a Pupil Response linked to an Intervention or dated context,
So that response evidence sits with the provision trail.

**Acceptance Criteria:**

**Given** an in-scope Pupil and permission to record Interventions  
**When** they submit a Pupil Response (FR-16)  
**Then** the Response links to an Intervention id or a dated context as required by validation rules  
**And** Support Staff use the same capture forms as Teachers (PRD story default)  
**And** invalid links return 422 with field errors (`aria-describedby` in UI) (UX-DR19)  
**And** submitted Responses are `submitted` lifecycle and Audit Event–logged

### Story 3.4: Draft Evidence Records

As a capture User,
I want to save Observation/Intervention/Pupil Response drafts and submit later,
So that interrupted classroom work is not lost and drafts never enter SRE.

**Acceptance Criteria:**

**Given** a started capture form  
**When** the User saves a draft (FR-18, AD-16)  
**Then** lifecycle is `draft` and the record is excluded from SRE triggers  
**And** drafts are visible to the author and the School SENCO only  
**And** submitting a draft transitions to `submitted` with the same validations as a direct submit  
**And** Drafts list is reachable from Web sidebar and Hybrid bottom nav (UX-DR5, UX-DR9)

### Story 3.5: Hybrid capture, offline queue, and client parity

As a Teacher on a phone or tablet,
I want Hybrid capture with offline draft queueing and clear not-on-server labelling,
So that connectivity drops do not fake Evidence Base state (UJ-1 failure path).

**Acceptance Criteria:**

**Given** the Hybrid Client (Capacitor + same Vue SPA) (FR-19, AD-9)  
**When** the device is offline during capture  
**Then** the draft is queued locally with banner: “Saved on this device — not on the Evidence Base until you reconnect” (UX-DR18)  
**And** on reconnect, the API validates; Pupil identity conflicts: server wins; author retains the local draft for resolution  
**And** a capture submitted from Hybrid is indistinguishable in the Evidence Base except Audit Event client-type  
**And** Web and Hybrid share identical server validations — no divergent business rules in Vue  
**And** bottom nav supports Home, Pupils, Capture, Drafts, More for Teacher/Support Staff (UX-DR5)

### Story 3.6: Connector-sourced evidence without parallel logging

As a SENCO,
I want events that arrived via Connector with equivalent mapped fields to count without retyping,
So that staff are not mandated to double-log the same provision.

**Acceptance Criteria:**

**Given** an Evidence Record created or updated from a Connector/import mapping with equivalent fields (FR-17)  
**When** a Teacher views that Pupil’s capture obligations for that event  
**Then** the product does not require the same event to be typed again into GuidelyEdu  
**And** UI copy does not instruct parallel logging for Connector-equivalent records  
**And** if mapping is incomplete, the User can capture a new structured record to fill Gaps without deleting the Connector row  
**And** this story may ship with import-mapped evidence first; live Connector upsert behaviour is completed in Epic 6 without changing this rule

## Epic 4: Evidence Base & Statutory Reasoning

Authorised staff can work a chronological Evidence Base; Platform Operator publishes versioned Ontology/Rules; SRE produces deterministic Determinations, Gaps, Documentation Status, Reasoning Pathways, and Overrides — never diagnoses.

**FRs:** FR-20–FR-32, FR-34–FR-37, FR-56, FR-60  
**NFRs / Arch:** NFR-2, NFR-11, AD-4–AD-6, AD-10 (disclaimer adjacency), AD-15–AD-17, AD-19  
**UX-DRs:** UX-DR7, UX-DR9, UX-DR11, UX-DR13, UX-DR15, UX-DR18–UX-DR19, UX-DR21–UX-DR22, UX-DR24 (senco-evidence-base mock)

### Story 4.1: Chronological Evidence Base

As a SENCO or other authorised Role,
I want a Pupil Evidence Base in time order with type filters,
So that I can review the full documentation trail for a Review Cycle.

**Acceptance Criteria:**

**Given** a Pupil with Evidence Records in scope  
**When** an authorised User opens the Evidence Base (FR-20)  
**Then** records appear chronologically and filter by Observation, Intervention, Pupil Response, review note, import  
**And** Teachers/Support Staff see only assigned Pupils; School Leader is read-mostly (no edit)  
**And** empty state offers Capture CTA for Teachers (UX-DR18)  
**And** FR-35 disclaimer banner is visible on the Evidence Base (UX-DR11)  
**And** UI aligns with `mockups/senco-evidence-base.html` where spines win on conflict (UX-DR24)

### Story 4.2: Amend Evidence with history

As an author or SENCO,
I want to correct an Evidence Record while retaining prior versions,
So that mistakes can be fixed without erasing accountability.

**Acceptance Criteria:**

**Given** a `submitted` Evidence Record the User is permitted to amend (FR-21)  
**When** they save a correction  
**Then** the previous version remains retrievable  
**And** amend enqueues `SreReevaluatePupil` for that Pupil (AD-17)  
**And** Audit Event records the amendment  
**And** School Leader cannot amend Evidence Records (UX-DR21)

### Story 4.3: SENCO review notes

As a SENCO,
I want to add a review note Evidence Record marked as professional commentary,
So that judgement context is in the Evidence Base without pretending to be classroom observation.

**Acceptance Criteria:**

**Given** a Pupil in the SENCO’s School  
**When** SENCO adds a review note (FR-22)  
**Then** it is stored as an Evidence Record type `review_note` (or equivalent), in-scope for documentation, clearly labelled commentary  
**And** it is visible in the chronological Evidence Base  
**And** Teachers cannot create review notes

### Story 4.4: Versioned Ontology and evidence mapping

As a Platform Operator / system,
I want a versioned Ontology with five domains and automatic mapping of submitted evidence to Ontology terms,
So that Rules evaluate against stable, citable terms.

**Acceptance Criteria:**

**Given** Ontology administration capability  
**When** an Ontology version is loaded for a Tenant (FR-23, FR-25)  
**Then** Need, Provision, Outcome framework, Threshold Definitions, and Relationship Mappings are exposed  
**And** on submit/import, each Evidence Record maps to required Ontology terms for Rules (FR-24)  
**And** publishing a new version does not mutate past Determinations’ stored Ontology version ids  
**And** unmapped required terms block submit with 422 field errors

### Story 4.5: Rule Library with initial and escalation coverage

As a Platform Operator,
I want Rules as versioned data with condition/evaluation/outcome shape, including an initial subset plus escalation/review-threshold Rules,
So that SRE can evaluate Graduated Response documentation without hard-coded controller logic.

**Acceptance Criteria:**

**Given** Domain\Ontology Rule tables (FR-27, AD-5)  
**When** the initial Rule Library version is published (FR-30)  
**Then** common documentation/threshold scenarios are covered without claiming full SEND framework coverage  
**And** escalation and review-threshold Rules are included (FR-31) without changing the four-dimension model  
**And** each Rule belongs to a Rule Library version id  
**And** no LLM is used to author runtime Rule outcomes (NFR-11)

### Story 4.6: Deterministic SRE evaluator (four dimensions)

As a SENCO,
I want every SRE run to produce Determinations across four dimensions deterministically,
So that the same evidence and versions always yield the same evaluation.

**Acceptance Criteria:**

**Given** a Pupil Evidence Base snapshot and pinned Ontology + Rule Library versions  
**When** the in-process SRE evaluator runs (FR-26, FR-29, AD-4)  
**Then** Determinations exist for Sequential Compliance, Evidential Sufficiency, Proportionality, and Outcome Progression — or explicit “no applicable Rule” / Uncovered per dimension  
**And** identical inputs always produce identical outputs (fixture-tested)  
**And** SRE does not call generative models  
**And** a run for ≤500 Evidence Records completes within 3 seconds (NFR-2)  
**And** only `Domain\Sre` writes Determinations (AD-15)

### Story 4.7: Async re-evaluation, Gaps, and Documentation Status

As a staff User,
I want evidence changes to re-evaluate the Pupil asynchronously with visible status and derived Gaps,
So that capture stays fast and documentation state stays accurate.

**Acceptance Criteria:**

**Given** create/amend/import of `submitted` Evidence (or other AD-17 triggers)  
**When** the write commits (FR-28, AD-6)  
**Then** `SreReevaluatePupil(tenant_id, pupil_id, reason)` is enqueued and Documentation Status becomes `evaluating` until the job completes (FR-37, AD-16)  
**And** drafts never enqueue SRE (FR-18)  
**And** Gaps are derived rows written only by `Domain\Sre` from current Determinations (FR-36, AD-15) — not hand-edited  
**And** allowed Documentation Status values are exactly `ready` | `gaps` | `uncovered` | `not-started` | `evaluating`  
**And** UI shows inline spinner on Pupil status while evaluating; no full-page block unless >3s (UX-DR18)  
**And** StatusPill uses text + icon (UX-DR7)

### Story 4.8: Reasoning Pathway and Gap list UI

As a SENCO (or permitted Role),
I want to open Gaps and the Reasoning Pathway for any Determination,
So that I can see which Rule, evidence, and conditions produced the outcome (UJ-2).

**Acceptance Criteria:**

**Given** current or historical Determinations for a Pupil  
**When** SENCO opens a Gap or Determination (FR-32, FR-36)  
**Then** Reasoning Pathway shows Rule id/name/version → conditions → Evidence Record links → Determination (UX-DR13)  
**And** pathway is readable without colour alone and uses heading hierarchy (UX-DR19)  
**And** Uncovered dimensions are labelled Uncovered, not success (UX-DR18)  
**And** School Leader may open Reasoning Pathway read-only on drill-down (UX-DR21)  
**And** product copy never presents confidence % or diagnosis language (FR-35, UX-DR11)

### Story 4.9: Override with mandatory rationale

As a SENCO or School Leader,
I want to Override a failing or insufficient Determination with a mandatory rationale,
So that a Review Cycle can proceed without deleting the Determination history.

**Acceptance Criteria:**

**Given** a failing/insufficient Determination  
**When** SENCO or School Leader submits an Override (FR-34)  
**Then** rationale shorter than 20 characters is rejected  
**And** Override is owned by `Domain\Sre`, appends an Override record, and does not mutate Evidence (AD-15)  
**And** Determination history remains intact  
**And** Override enqueues status recompute via `SreReevaluatePupil` as needed (AD-17)  
**And** Override dialog matches UX-DR15; Teachers cannot Override

### Story 4.10: Operator Ontology/Rule publish and controlled roll-forward

As a Platform Operator,
I want controlled publish of Ontology/Rule Library versions and Tenant roll-forward that preserves historical Determinations,
So that statutory encoding can evolve without rewriting the past.

**Acceptance Criteria:**

**Given** Platform Operator identity (not a School Teacher Role)  
**When** they publish a new Ontology/Rule Library version into a Tenant (FR-56)  
**Then** publish is an explicit step (artisan and/or minimal admin), not an accidental save  
**And** roll-forward to Tenants preserves historical Determinations’ version citations (FR-60, FR-25)  
**And** publish triggers re-eval jobs per AD-17 for affected Pupils as designed  
**And** School Roles cannot publish Ontology/Rule versions

## Epic 5: Review Cycles, Documentation Outputs & School Report

SENCO runs Review Cycles and generates confirmed Documentation Outputs; School Leader/SENCO see School Report readiness within the performance bound.

**FRs:** FR-33, FR-38–FR-46  
**NFRs / Arch:** AD-10, AD-15 (Outputs read-only on Determinations), AD-19; NFR performance for FR-46  
**UX-DRs:** UX-DR9, UX-DR11, UX-DR14, UX-DR17, UX-DR21–UX-DR22, UX-DR24 (school-report mock)

### Story 5.1: Create Review Cycle and due list

As a SENCO,
I want to create Review Cycles and see those due in a date window,
So that Annual Reviews and other cycles are planned from live documentation state.

**Acceptance Criteria:**

**Given** a Pupil in the SENCO’s School  
**When** SENCO creates a Review Cycle with type (including Annual Review), due date, and optional EHCP link flag (FR-42)  
**Then** the cycle is stored and visible on the Pupil and due list  
**And** SENCO and School Leader can list Review Cycles due in a date window for their School (FR-43)  
**And** create/close that changes evaluation context enqueues `SreReevaluatePupil` when required (AD-17)  
**And** top bar search can find Review Cycles within scope (UX-DR20)

### Story 5.2: Automated Review Cycle management (flagged)

As a Tenant Admin / SENCO,
I want optional automated create/roll-forward of Review Cycles from Tenant policy,
So that Trusts can reduce manual cycle admin when ready.

**Acceptance Criteria:**

**Given** Review Cycle automation policy configured for a Tenant (FR-44)  
**When** the policy is enabled  
**Then** the product can create or roll forward Review Cycles per policy  
**And** default remains manual close when policy is off (PRD assumption)  
**And** when the feature flag/policy is off, automation routes are not-available (AD-8)

### Story 5.3: Confirmed Documentation Outputs (review summary & EHCP pack)

As a SENCO,
I want to generate a review summary and EHCP evidence pack only after explicit confirmation,
So that outputs support professional judgement and are not auto-issued statutory decisions (UJ-2).

**Acceptance Criteria:**

**Given** a Pupil and Review Cycle with Evidence/Determinations available  
**When** SENCO generates a review summary (FR-38) or EHCP evidence pack (FR-39)  
**Then** generation requires confirmation payload: named User + disclaimer acknowledgement (FR-33, AD-10, UX-DR14)  
**And** generation without confirmation is blocked with a clear toast/error  
**And** output cites Evidence Record and Determination ids and shows FR-35 disclaimer (UX-DR11)  
**And** `Domain\Outputs` reads Determinations/Evidence/Gaps and does not write Determinations or Gaps (AD-15)

### Story 5.4: Export-ready files and advanced packs

As a SENCO,
I want downloadable portable files plus audit sidecars, including flagged tribunal/inspection packs,
So that packs are usable outside GuidelyEdu with provenance intact.

**Acceptance Criteria:**

**Given** a confirmed Documentation Output  
**When** SENCO downloads it (FR-40)  
**Then** a portable file (PDF or equivalent) plus structured sidecar are produced  
**And** file bytes live in encrypted object storage; DB holds metadata + checksum (AD-10)  
**And** tribunal/inspection pack types are available only when Tenant flag is on (FR-41); flag off → not-available (UX-DR17)  
**And** advanced Audit Event trace is attached for those pack types  
**And** exports emit Audit Events (FR-10)

### Story 5.5: School Report readiness

As a School Leader or SENCO,
I want a School Report of documentation status counts, open Gaps, and Review Cycles due,
So that I know whether the School is review-ready without attendance or budget KPIs (UJ-3).

**Acceptance Criteria:**

**Given** in-scope Pupils for the School  
**When** School Leader or SENCO opens School Report (FR-45)  
**Then** figures equal the sum of in-scope Pupils (no sampling)  
**And** counts use Documentation Status enum; `evaluating` is reported separately or as in-flight  
**And** no attendance, budget, or general curriculum KPIs appear  
**And** report generates in under 5 seconds for ≤500 Pupils (FR-46)  
**And** School Leader drills to lists but does not edit Evidence Records (UX-DR21)  
**And** UI aligns with `mockups/school-report.html` where spines win (UX-DR24)  
**And** `Domain\Reporting` only reads aggregates — no informal Rule logic in SQL (AD-15)

## Epic 6: MIS Connectors

Tenant Admin can configure a Connector with field sharing, sync without duplication, and enable a Pilot-chosen standardised adapter — Import Template remains the fallback.

**FRs:** FR-48, FR-49, FR-50  
**NFRs / Arch:** AD-11, AD-17; OQ-4 Pilot gate  
**UX-DRs:** UX-DR9 (Connectors), UX-DR17

### Story 6.1: Connector configuration and field sharing

As a Tenant Admin,
I want to enable a Connector with School-controlled field sharing,
So that only approved MIS fields enter GuidelyEdu.

**Acceptance Criteria:**

**Given** Tenant Admin (or implementation operator) privileges  
**When** they enable a Connector and set field sharing (FR-48)  
**Then** disabled fields never appear in GuidelyEdu after sync  
**And** Connector secrets are never displayed back in the UI  
**And** configuration is Tenant-scoped and Audit Event–logged  
**And** Import Template remains available regardless of Connector state (AD-11)

### Story 6.2: Sync upsert without duplication

As a Tenant Admin / SENCO,
I want Connector payloads to upsert by MIS key,
So that repeated syncs do not create duplicate Pupils or evidence.

**Acceptance Criteria:**

**Given** a configured Connector  
**When** the same Pupil payload syncs twice (FR-49)  
**Then** exactly one Pupil record exists for that MIS key  
**And** Evidence Records from MIS carry external ids  
**And** sync failures are visible to platform operators within observability targets (NFR-5)  
**And** successful sync affecting a Pupil enqueues `SreReevaluatePupil` when submitted evidence changes (AD-17)

### Story 6.3: Standardised MIS Connector adapter (Pilot primary)

As a Platform Operator / Tenant Admin,
I want a repeatable standardised Connector adapter for the Pilot-chosen MIS/hub,
So that new Schools can enable a supported Connector without engineering one-offs.

**Acceptance Criteria:**

**Given** OQ-4 has named exactly one primary adapter family (Wonde, Groupcall, or one direct MIS API) before implementation beyond the interface  
**When** that adapter is implemented behind the shared Connector interface (FR-50, AD-11)  
**Then** a new School in an existing Trust can enable the supported Connector without code changes  
**And** unsupported MIS still works via Import Template (FR-47)  
**And** until OQ-4 is named, only the adapter interface + Import Template path are required; this story is blocked on the Pilot adapter decision  
**And** flag/config can disable Connector types per Tenant (FR-4)

## Epic 7: Trust Oversight & Advanced Signals

Trust roles see multi-School Indicators, escalations, and (when flagged) benchmarking/trends, compliance alerts, and minimised safeguarding context.

**FRs:** FR-51, FR-52, FR-53, FR-58, FR-59  
**NFRs / Arch:** AD-8, AD-15 (Reporting read-only)  
**UX-DRs:** UX-DR8 (Trust IA), UX-DR9, UX-DR17, UX-DR22 (UJ-5)

### Story 7.1: Trust multi-academy Indicators

As a Trust SEND Lead,
I want Indicators across enabled Schools (lateness, Gap density, status mix),
So that I can spot documentation risk without dumping Pupil narratives (UJ-5).

**Acceptance Criteria:**

**Given** Trust features flag on for the Tenant  
**When** Trust SEND Lead opens Trust Dashboard (FR-51)  
**Then** Indicators show Review Cycle lateness, Gap density, and Documentation Status mix across enabled Schools  
**And** default views do not dump Pupil-level narrative evidence  
**And** drill-down to a School uses School Report permissions  
**And** Trust Executive default remains aggregate Indicators only  
**And** flag off → not-available page, not a zeroed “perfect” chart (UX-DR17, AD-8)

### Story 7.2: Escalation Indicators

As a Trust SEND Lead,
I want Schools or Pupils flagged by encoded escalation Rules with explainable citations,
So that outliers get support before board/inspection surprise.

**Acceptance Criteria:**

**Given** Trust Dashboard enabled and escalation Rules in the Rule Library  
**When** escalation Indicators are computed (FR-52)  
**Then** each flag cites which Rule produced it  
**And** flags are not presented as diagnoses (FR-35)  
**And** drill path goes to School Report / scoped lists, not unrestricted Teacher-style evidence search

### Story 7.3: Portfolio benchmarking and trends (flagged)

As a Trust SEND Lead,
I want optional cross-School benchmarking and aggregate trends,
So that portfolio documentation patterns are visible when the Tenant enables them.

**Acceptance Criteria:**

**Given** portfolio benchmarking Tenant flag (FR-53)  
**When** the flag is off  
**Then** routes return not-available (not required for Pilot SM validation)  
**And** when on, minimum Indicators include Review Cycle lateness rate, Gap density, Documentation Status mix, plus optional month-over-month trends of those aggregates  
**And** any forecasting is labelled Indicator extrapolation and stays School/Trust aggregate only — never Pupil prognosis

### Story 7.4: Compliance risk alerts (flagged)

As a SENCO or Trust role,
I want alerts when documentation risk Indicators cross thresholds,
So that overdue or high-Gap situations surface without manual spreadsheet watches.

**Acceptance Criteria:**

**Given** compliance alert feature enabled for the Tenant (FR-59)  
**When** an Indicator crosses a configured threshold at School or Trust level  
**Then** an alert is created and visible to permitted Roles  
**And** alert generation is Audit Event–logged  
**And** flag off → no alert spam / not-available for alert admin surfaces

### Story 7.5: Minimised safeguarding signal ingest (flagged)

As a SENCO or School Leader,
I want optional minimised safeguarding context signals (not case notes),
So that documentation work can note presence/severity category without becoming a safeguarding case system.

**Acceptance Criteria:**

**Given** safeguarding ingest Tenant flag (FR-58)  
**When** the flag is off (default)  
**Then** ingest endpoints reject or no-op without storing signals  
**And** when on, payload accepts presence/severity category only — no free-text case notes or full safeguarding records  
**And** signals are Audit Event–logged and Role-restricted to SENCO + School Leader only  
**And** UI labels the surface as context, not casework; product remains not a safeguarding system

