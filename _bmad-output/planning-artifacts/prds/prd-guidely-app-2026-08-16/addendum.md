# Addendum — GuidelyEdu PRD (2026-08-16)

This file holds delivery mechanism, stack, and rejected alternatives. It is **not** the product contract. Capabilities live in `prd.md`.

## 1. Delivery stack (user-directed, 2026-08-16)

Paul instructed that GuidelyEdu will be built as a **Laravel + Vue** application and converted into a **hybrid mobile app**. The March 2026 business plan specified PostgreSQL, a cloud application service, SRE as an internal backend, Rule tables in the database, and Protégé for Ontology authoring. It did **not** name Laravel or Vue.

**Decision:** Honour the user stack for application code; keep the business plan’s data and knowledge-engineering choices.

**Build scope (2026-08-20):** Implement **FR-1–FR-60** in the first Laravel release. Tenant feature flags control which surfaces a Pilot or Trust sees; they do not remove FRs from the backlog. Stack versions are pinned in Architecture AD spine (Laravel 13 / Vue 3.5 / Capacitor 8 / PostgreSQL 18 family as of Aug 2026 verification).

| Layer | Choice | Reason |
|---|---|---|
| API / domain | Laravel (see Architecture for minor version), JSON API | One server language for RBAC, Audit Events, tenancy, jobs (SRE, sync). JSON API rather than Inertia so the Hybrid Client and Web Client share the same contracts. |
| Web Client | Vue 3 SPA under `resources/js` (Vue Router; **not Inertia**) | User request; pairs with hybrid packaging; classic root Laravel layout (2026-09-05). |
| Hybrid Client | Same Vue SPA inside Capacitor shell | User asked to *convert* the Vue app; OQ-1 closed — Hybrid in first build. |
| Data | PostgreSQL | Business plan §3.5; JSON/JSONB for semi-structured Evidence Record payloads where needed. |
| Ontology authoring | Protégé (or equivalent OWL tooling) off-platform | Business plan; runtime consumes a published, versioned export. |
| Queues / workers | Laravel queues for SRE re-eval and Connector sync | Keeps HTTP request path short (NFR-1, NFR-2). |
| Auth | Web: session; Hybrid: token (Architecture AD-18) | FR-8; SSO enablement path (FR-9). |
| Files | Encrypted object storage for Documentation Outputs | FR-40. |

Rejected in this addendum:

- **Inertia + Blade as the only UI** — harder to wrap as hybrid without a second rendering model.
- **Separate React Native / native rewrite** — contradicts “convert the Vue app”.
- **Probabilistic AI microservice** — contradicts the SRE thesis in `prd.md`.
- **Implementing the October 2025 wireframe IA** — different product; see source analysis canvas.
- **Deferring Hybrid to post-v1** — rejected; Hybrid locked in first build.

## 2. Hybrid mapping

| Role | Web Client | Hybrid Client |
|---|---|---|
| Teacher / Support Staff | Yes | Primary surface for FR-14–FR-16 |
| SENCO | Primary (Review Cycle, outputs, Reasoning Pathway) | Yes, review + Gap list |
| School Leader | Primary (School Report) | Optional |
| Tenant Admin / Trust roles | Web only for admin density | Hybrid not required for these Roles |

Offline: queue drafts on device; never imply they are on the Evidence Base until the API accepts them (`prd.md` FR-19). Conflict policy: server wins on Pupil identity; device drafts remain author-only until synced.

## 3. Permission matrix (product artefact to implement)

Enforced on the API, not only the Vue router.

| Capability | Teacher | Support Staff | SENCO | School Leader | Tenant Admin | Trust SEND Lead* | Trust Executive* | Platform Operator† |
|---|---|---|---|---|---|---|---|---|
| Capture Observation / Intervention / Pupil Response | assigned Pupils | assigned Pupils | School | no | no | no | no | no |
| View Evidence Base | assigned | assigned | School | School (read) | no Pupil content | drill to School | aggregates | support read (audit) |
| Run/view Determinations | own captures after submit | same | School | School | no | School drill | Indicators | support |
| Override | no | no | yes | yes | no | no | no | no |
| Documentation Output | no | no | yes | view | no | view School | no | no |
| Import Template / Connector settings | no | no | Import | no | yes | limited | no | Pilot toolkit |
| User admin | no | no | no | no | yes | no | no | cross-Tenant ops |
| Publish Ontology / Rule versions | no | no | no | no | no | no | no | yes |

\* Roles exist only when Trust features are enabled.  
† GuidelyEdu staff — not a Tenant Role (`prd.md` Glossary / FR-6).

## 4. SRE runtime (how, not what)

- Rules as data (tables), versioned, executed in a pure function/service so FR-29 can be tested with fixtures.
- Do not call an LLM to produce Determinations or Reasoning Pathways.
- Ontology export pipeline: Protégé → versioned artefacts → Laravel import command → publish to Tenant.

## 5. Connectors

Business plan cites Wonde and Groupcall as *illustrative* fabrics, plus direct MIS APIs. **OQ-4 gate:** Pilot names exactly one primary adapter before FR-48–50 implementation beyond the adapter interface. Import Template is the contractual fallback and available immediately.

## 6. Programme controls (not app features)

From business plan §9, required before Trust procurement: BCP/DR, encrypted backups, incident response, DPIA pack, ICO registration, contracts, Cyber Essentials roadmap. Track in operating a Trust-readiness checklist, not as Vue screens. **Pilot data gates** also require Tenant retention policy configured (no invented product default) and UK residency deployment (AD-14).

## 7. Options considered (product, not stack)

| Option | Outcome | Why |
|---|---|---|
| Build the wireframe family-care product | Rejected | Paul selected business plan only. |
| First build = Pilot-only FRs; Phase 2–3 deferred | Rejected (2026-08-20) | Paul directed every FR (FR-1–FR-60) into the first Laravel build. Phase labels remain GTM/Tenant-flag sequencing only. |
| Pupils/Parents as Users in v1 | Rejected | Operators in the plan are staff; adding family accounts changes GDPR, safeguarding, and GTM. |
| Web-only first release (no Hybrid) | Rejected (2026-08-20) | Classroom capture requires Hybrid in first build. |
