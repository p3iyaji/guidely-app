---
title: GuidelyEdu
status: final
created: 2026-08-16
updated: 2026-08-20
---

# PRD: GuidelyEdu

## 0. Document Purpose

This PRD is the product contract for GuidelyEdu: the build team, UX, architecture, and story workflows. It is derived only from *GuidelyEdu Confidential Business Plan* (March 2026). The October 2025 wireframe PDF is a different product concept and is out of scope for product IA (visual style may inform UX DESIGN.md only). Features are grouped; functional requirements use globally numbered IDs (FR-1…). Vocabulary is Glossary-locked. Inferences the business plan does not state are tagged `[ASSUMPTION]` and indexed in §22. Stack choices (Laravel, Vue, hybrid packaging) are delivery constraints in `addendum.md`.

**Companions (final):** UX `../ux-designs/ux-guidely-app-2026-08-20/` · Architecture `../architecture/architecture-guidely-app-2026-08-20/ARCHITECTURE-SPINE.md`.

**Build-scope decision (2026-08-20):** The first Laravel + Vue (+ hybrid) build implements **every FR in this PRD (FR-1–FR-60)**. Business-plan Phase labels (1 / 2 / 3) remain commercial / Tenant-flag sequencing only. Flags hide Trust/advanced surfaces from a Pilot Tenant without removing capability from the codebase.

**Pilot entry gates (block live Pupil data, not story writing):** legal retention policy configured (no invented product default); UK/adequate residency confirmed in deployment; DPIA pack started; OQ-4 primary Connector adapter named before FR-48–50 implementation (Import Template remains available immediately).

## 1. Vision

GuidelyEdu is a cloud, multi-tenant interpretation and documentation layer for Special Educational Needs and Disabilities (SEND) in UK Schools and Trusts. It does not replace a Management Information System (MIS). It sits beside SIMS, Arbor, Bromcom and equivalents, turning fragmented Observations, Interventions, review notes and related Evidence Records into structured, explainable, review-ready Documentation Outputs.

The product thesis is narrow: the sector already stores records; it does not consistently **interpret** them against SEND Code of Practice conditions while work is happening. GuidelyEdu’s core is a machine-readable Ontology plus a deterministic Statutory Reasoning Engine (SRE). The SRE evaluates Sequential Compliance, Evidential Sufficiency, Proportionality and Outcome Progression, then shows the Rule, evidence considered, and Reasoning Pathway. Professional judgement stays with the SENCO and School Leader. GuidelyEdu must never present a Determination as a diagnostic, funding, or placement decision.

Without that layer, SENCOs reconstruct cases from spreadsheets, inboxes and local trackers under rising caseloads, EHCP volumes and Trust-level accountability. GuidelyEdu’s job is earlier Gap detection, less last-minute collation, and Documentation Outputs that a school can defend in an Annual Review, inspection or Trust oversight meeting.

## 2. Why Now

England had more than 1.7 million pupils with SEN in 2024/25 and 638,745 active EHC plans in January 2025 (DfE figures cited in the business plan). High-needs funding is a major budget line. Trusts, not individual Schools, increasingly hold budget and governance risk. Existing MIS and SEND admin tools organise records; they do not encode Graduated Response logic. GuidelyEdu’s first commercial year is modelled as unpaid Pilots; the product must be usable in live School workflows before Trust procurement.

## 3. Target User

### 3.1 Jobs To Be Done

- **Functional (Teacher / Support Staff):** Record an Observation, Intervention and Pupil Response once, in a format that will still make sense at review time, without maintaining a parallel tracker. `[ASSUMPTION: classroom use is the primary mobile job.]`
- **Functional (SENCO):** See, before an Annual Review, whether the Evidence Base for a Pupil meets encoded thresholds, and generate a Documentation Output instead of assembling Word files by hand.
- **Functional (School Leader):** Confirm documentation readiness and Gap patterns without reading every case file.
- **Functional (Trust SEND Lead / Trust Executive):** Compare documentation consistency and escalation Indicators across Schools in one Tenant.
- **Emotional / social:** Reduce fear that an inspection, Local Authority challenge or tribunal will expose an incomplete trail, while remaining clearly *support* for judgement, not a black box that “decides” SEND.
- **Contextual:** Work inside existing MIS-centric routines; adopt incrementally (one cohort or School) under workload pressure.

### 3.2 Non-Users (v1)

- Pupils (no Pupil-facing learning or LMS).
- Parents as operators (they may be *subjects* of consent/process, not account holders). `[ASSUMPTION]`
- Clinicians, therapists, CAMHS, or Local Authority caseworkers as product operators.
- Schools outside England/UK statutory SEND practice. `[ASSUMPTION: England-first; UK-wide later only if Ontology versions support it.]`
- Trust procurement users who only need a brochure site (marketing site is out of product v1).

### 3.3 Key User Journeys

Journeys inferred from business plan §3.7–3.9 and stakeholder tables. `[ASSUMPTION: named protagonists and beat order are inferred; they were not narrated in the source.]`

- **UJ-1. James logs today’s Intervention without creating a second record.**
  - **Persona + context:** James, Year 4 Teacher, 28 in class, four on SEN Support. He already wrote something similar in a spreadsheet last term and lost it at Annual Review.
  - **Entry state:** Provisioned User, Role Teacher, authenticated on phone or laptop in the classroom after MIS sync or Import Template loaded the Pupil list.
  - **Path:** Opens GuidelyEdu → selects Pupil → records Observation + Intervention used + Pupil Response in the structured form → submits. SRE runs against the Evidence Base. Status and any Gap appear on the Pupil record.
  - **Climax:** He sees the Evidence Record attached to the Pupil’s Graduated Response timeline, not sitting in his inbox.
  - **Resolution:** Continues teaching. SENCO Aisha can see the new Evidence Record without chasing him.
  - **Edge case:** Connectivity drops mid-form. `[ASSUMPTION: hybrid client queues the draft locally and submits when online; User is told it is not yet on the Evidence Base.]`

- **UJ-2. Aisha prepares an Annual Review without reconstructing the year.**
  - **Persona + context:** Aisha, SENCO, 40+ Pupils on the School SEND cohort, two Annual Reviews next week.
  - **Entry state:** Authenticated as SENCO on web (desktop primary).
  - **Path:** Opens Review Cycle due list → opens Pupil → reads Determinations and Gaps across Sequential Compliance, Evidential Sufficiency, Proportionality, Outcome Progression → inspects Reasoning Pathway for any failing Rule → asks James for one missing Intervention log **or** records SENCO-level review notes → generates Documentation Output (review summary / EHCP evidence pack).
  - **Climax:** She can point to which Rules passed/failed and which Evidence Records were considered.
  - **Resolution:** Documentation Output stored with version, Audit Event, and human confirmation that judgement is hers.
  - **Edge case:** SRE flags insufficiency; Aisha disagrees. She must be able to proceed with a recorded override/rationale. The Determination history remains. Realizes FR-32.

- **UJ-3. Priya checks inspection-readiness on Friday afternoon.**
  - **Persona + context:** Priya, Headteacher, Ofsted-adjacent anxiety, not a daily GuidelyEdu power user.
  - **Entry state:** School Leader Role, school-level School Report.
  - **Path:** Opens School Report → sees counts of Review Cycles due, Pupils with open Gaps, documentation status — not a clinical caseload tool.
  - **Climax:** She knows whether Aisha’s team is review-ready this month.
  - **Resolution:** Asks Aisha about exceptions; does not edit Evidence Records. `[ASSUMPTION: School Leader is read-mostly on Pupil evidence.]`

- **UJ-4. MIS go-live is delayed, so the Pilot still starts Monday.**
  - **Persona + context:** Aisha plus founder-led implementation. Trust IT has not authorised Wonde/Groupcall yet.
  - **Entry state:** Empty Pupil list in Tenant.
  - **Path:** Upload Import Template (Pupils, Need categories, existing Interventions) → validate errors → Teachers can capture Evidence Records the same day.
  - **Climax:** No parallel-logging excuse because GuidelyEdu has a usable cohort without the Connector.
  - **Resolution:** When Connector is authorised, subsequent sync does not duplicate Pupils. Realizes FR-47, FR-48.

- **UJ-5. Daniel sees which academies are drifting.**
  - **Persona + context:** Daniel, Trust SEND Lead, eight Schools, board wants comparable documentation quality.
  - **Entry state:** Trust-scoped Role, Trust Dashboard enabled for the Tenant.
  - **Path:** Opens Trust Dashboard → Indicators for review lateness, Gap density, escalation — drill into one School, not into replacing Aisha’s workflow.
  - **Climax:** One School is an outlier; he schedules support rather than discovering it at inspection.
  - **Resolution:** No Pupil-identifying export beyond authorised Roles and purpose. Realizes FR-51–FR-53.

## 4. Glossary

- **GuidelyEdu** — The product: a complementary SEND interpretation and documentation layer. Not an MIS.
- **Tenant** — One contracted School or Trust environment with logical data separation from other customers.
- **Trust** — Multi-Academy Trust: the primary economic buyer. Contains one or more Schools.
- **School** — An academy or school that operates a SEND cohort. The unit of day-to-day workflow. Cardinality: many Schools per Trust Tenant; a single-School Tenant is allowed.
- **User** — A natural person with a provisioned account in a Tenant.
- **Role** — Permission set: Teacher, Support Staff, SENCO, School Leader, Trust SEND Lead, Trust Executive, Tenant Admin. A User has one or more Roles in one School or Trust scope.
- **Teacher** — User who records classroom Observation, Intervention and Pupil Response.
- **Support Staff** — User who records support-session Evidence Records under SENCO-visible workflows.
- **SENCO** — Special Educational Needs Coordinator. Primary operator of Review Cycles, Gaps and Documentation Outputs.
- **School Leader** — Headteacher or SLT User. School-level oversight; not a second SENCO caseload tool.
- **Trust SEND Lead** — Trust-scoped User for cross-School consistency.
- **Trust Executive** — CEO/CFO/board-facing oversight consumer of Trust Dashboard Indicators.
- **Tenant Admin** — User who manages Users, Roles, Connector settings and retention configuration within the Tenant.
- **Pupil** — Child on a School roll who may have SEND documentation in GuidelyEdu. Not a User.
- **SEND** — Special Educational Needs and Disabilities as defined in England statutory guidance.
- **SEND Code of Practice** — DfE/DHSC *SEND Code of Practice: 0 to 25 years*. Source of Ontology concepts.
- **SEN Support** — SEND provision below EHCP.
- **EHCP** — Education, Health and Care Plan.
- **Graduated Response** — Assess–Plan–Do–Review cycle of SEND support.
- **Observation** — Structured Evidence Record of what was noticed about a Pupil in context.
- **Intervention** — Structured Evidence Record of provision delivered, mapped in the Ontology to Need.
- **Pupil Response** — Structured Evidence Record of how the Pupil responded to an Intervention or context.
- **Evidence Record** — One Observation, Intervention, Pupil Response, review note, or imported record in the Evidence Base.
- **Evidence Base** — The ordered set of Evidence Records for one Pupil in one School.
- **Need** — Ontology class for primary/secondary areas of need.
- **Provision** — Ontology class for Interventions and support types.
- **Outcome** — Ontology class for progress indicators aligned to objectives and Review Cycles.
- **Ontology** — Proprietary, versioned, machine-readable SEND Statutory Ontology (Need, Provision, Outcome, Threshold Definitions, Relationship Mappings).
- **Rule** — A versioned condition + evaluation + Determination used by the SRE. Lives in the Rule Library.
- **Rule Library** — The set of executable Rules for an Ontology version.
- **SRE** — Statutory Reasoning Engine. Deterministic, not probabilistic. Evaluates the Evidence Base against Rules.
- **Determination** — SRE result for one Rule or dimension at a point in time (met / not met / insufficient evidence), with Reasoning Pathway.
- **Reasoning Pathway** — Explainability payload: Rule identity, Evidence Records considered, condition outcomes, resulting Determination.
- **Gap** — A missing or weak documentation condition the SRE has marked as not met or insufficient.
- **Documentation Output** — Generated artefact (review summary, EHCP evidence pack, later tribunal/inspection pack) from evaluated evidence. Always traceable to Evidence Records and Determinations.
- **Review Cycle** — A dated SEND review window for a Pupil (including Annual Review).
- **Annual Review** — Statutory EHCP review event modelled as a Review Cycle type.
- **School Report** — School-scoped reporting of documentation status, Gaps and Review Cycle due dates.
- **Trust Dashboard** — Cross-School governance surface. Configurable per Tenant (FR-4).
- **Indicator** — Aggregated, role-appropriate metric on a School Report or Trust Dashboard.
- **MIS** — School Management Information System (illustratively SIMS, Arbor, Bromcom). System of record for core pupil administration.
- **Connector** — Controlled integration to an MIS via API and/or education data hubs (illustratively Wonde, Groupcall).
- **Import Template** — Structured file upload used when a Connector is not yet authorised.
- **Audit Event** — Immutable log of a significant action (auth, access, modification, generation, export, override).
- **Pilot** — Time-bounded live School use for validation before paid conversion. Year 1 in the business plan is non-revenue Pilots.
- **Override** — SENCO or School Leader action that proceeds despite a failing Determination, with mandatory rationale. Does not delete the Determination. Owned by Domain\Sre (architecture AD-15).
- **Documentation Status** — Pupil-level enum: `ready` | `gaps` | `uncovered` | `not-started` | `evaluating` (UX + architecture locked 2026-08-20).
- **Platform Operator** — GuidelyEdu staff (not a School/Trust Tenant Role) who publishes Ontology/Rule Library versions and runs Pilot toolkit operations across Tenants.
- **Web Client** — Browser application for all Roles.
- **Hybrid Client** — Same product UX packaged for mobile devices, optimised for Evidence Record capture and SENCO review.

## 5. Features

### 5.1 Tenancy and organisation

**Description:** GuidelyEdu is multi-tenant. A Tenant is a School or a Trust. Trust Tenants contain Schools that can be enabled incrementally (one academy, then more) without a rebuild. Modular features (Trust Dashboard, Connectors, extra Documentation Output types) are switched by configuration. Realizes UJ-4, UJ-5.

**Functional Requirements:**

#### FR-1: Tenant isolation

Tenant Admin and platform operators work within a Tenant whose Pupil, Evidence Record and User data are logically separated from every other Tenant.

**Consequences (testable):**
- A User in Tenant A cannot read or write Tenant B objects by ID enumeration or API guess.
- List endpoints never return another Tenant’s rows.

#### FR-2: Trust contains Schools

A Trust Tenant can attach multiple Schools, each with its own Users, Pupils and Evidence Bases.

**Consequences (testable):**
- Creating a School does not copy another School’s Evidence Bases.
- A Teacher Role scoped to School X cannot open School Y Pupils.

#### FR-3: Incremental School activation

Tenant Admin can enable GuidelyEdu for a subset of Schools or a named Pupil cohort before Trust-wide rollout.

**Consequences (testable):**
- Disabled Schools do not appear in Teacher pickers.
- Enabling a second School does not require a new Tenant.

#### FR-4: Feature flags by configuration

Trust Dashboard, Connector types and advanced Documentation Output packs are enabled per Tenant/School by configuration, not custom code. All flagged capabilities are implemented in the first Laravel build; flags control Tenant exposure during Pilots and commercial rollout, not whether the feature exists in the product.

**Consequences (testable):**
- With Trust Dashboard disabled, Trust SEND Lead routes return not-available, not empty data that implies coverage.
- Enabling a flag for a Tenant does not require a new deployment.

### 5.2 Identity, Roles and access

**Description:** Hierarchical RBAC aligned to School and Trust structures. Authentication and User management are required in the first build; Single Sign-On is architected for connection without rebuilding tenancy (FR-9). Users are provisioned by the institution, not public registration. `[ASSUMPTION]` Significant auth events are Audit Events. Realizes all UJs.

**Functional Requirements:**

#### FR-5: Provisioned accounts

Tenant Admin (or implementation operator in Pilot) creates Users and assigns Roles and School/Trust scope.

**Consequences (testable):**
- There is no self-serve public registration endpoint in the product.
- A User without a Role cannot access Pupil data.

#### FR-6: Role set

The product supports Tenant Roles: Teacher, Support Staff, SENCO, School Leader, Tenant Admin; and, when Trust features are on, Trust SEND Lead and Trust Executive. Platform Operator is a separate GuidelyEdu operator identity (not a School Role).

**Consequences (testable):**
- Each Role has a documented permission matrix — **normative source: `addendum.md` §3**.
- Missing Role cannot be faked by UI hiding alone — API enforces the same rules.
- Platform Operator can publish Ontology/Rule versions (FR-56) without holding a School Teacher Role.

#### FR-7: Least privilege on Pupils

Users access only Pupils in their scoped Schools, and only fields required for their Role (data minimisation).

**Consequences (testable):**
- Teacher sees their assigned Pupils or School-configured class lists, not the full Trust.
- Trust Executive sees Indicators, not unrestricted Evidence Record text, unless a separate privilege is granted. `[ASSUMPTION: Trust Executive default is aggregate-only.]`

#### FR-8: Authentication

Users authenticate with credentials managed by GuidelyEdu (password/session). SSO may be enabled per Tenant when FR-9 is wired to a provider.

**Consequences (testable):**
- Failed logins are Audit Events and do not reveal whether the email exists. `[ASSUMPTION: enumeration protection.]`
- Session ends on logout from Web Client and Hybrid Client.

#### FR-9: SSO readiness

Identity architecture allows connection to the School/Trust SSO without rebuilding tenancy or Roles. The first Laravel build includes the external-id model and enablement path; binding a specific IdP is Tenant configuration, not a second product phase.

**Consequences (testable):**
- User identity has a stable external-id field even when unused.
- Enabling SSO does not duplicate Users when emails match. `[ASSUMPTION]`

**Out of Scope:** Shipping a pre-integrated IdP for every UK Trust by default — Tenant Admin / operator configures the chosen provider.

#### FR-10: Access logging

Authentication, record access, modifications, Documentation Output generation and exports emit Audit Events.

**Consequences (testable):**
- Opening a Pupil Evidence Base creates an Audit Event with User, Pupil, timestamp, Tenant.
- Audit Events cannot be edited by Tenant Admin.

### 5.3 Pupil directory and SEND cohort

**Description:** GuidelyEdu holds the SEND-working set of Pupils, Needs, and Review Cycle metadata. Core census-style administration stays in the MIS. GuidelyEdu must stay in sync when a Connector exists, and remain operable via Import Template when it does not. Realizes UJ-1, UJ-4.

**Functional Requirements:**

#### FR-11: Pupil records

SENCO and Tenant Admin can create and maintain Pupil working records (identity keys, School, year group, SEND status: SEN Support / EHCP / neither).

**Consequences (testable):**
- Two Pupils in one School cannot share the same MIS key when a key is present.
- Soft-deleted / left Pupils are excluded from active Teacher pickers but retained per retention policy.

#### FR-12: Need on the Pupil

SENCO can record primary and secondary Need categories from the Ontology Need taxonomy.

**Consequences (testable):**
- Free-text “diagnosis” is not the Need field; Need must be an Ontology term (plus optional notes).
- Changing Need re-queues SRE evaluation for that Pupil.

#### FR-13: Cohort and class assignment

Tenant Admin or SENCO can assign Pupils to classes/cohorts so Teachers see the right list.

**Consequences (testable):**
- A Teacher with no assignments sees an empty list, not all School Pupils. `[ASSUMPTION: assignment is required for Teachers.]`

### 5.4 Structured Evidence Capture

**Description:** Role-based workflows capture Observation, Intervention, Pupil Response and review activity in formats designed for SRE evaluation — not narrative dumping into a void. This is the daily path for Teacher and Support Staff; SENCO may add review notes. Realizes UJ-1. Hybrid Client is first-class for this feature.

**Functional Requirements:**

#### FR-14: Record Observation

Teacher and Support Staff can submit an Observation against a Pupil with required structured fields (Pupil, datetime or session context, what was observed, setting). `[ASSUMPTION: exact field list is Ontology-bound; UX specifies controls.]`

**Consequences (testable):**
- Submit is rejected without Pupil and body content.
- Saved Observation appears in that Pupil’s Evidence Base in time order.

#### FR-15: Record Intervention

Teacher, Support Staff and SENCO can record an Intervention linked to a Provision taxonomy term and the Pupil.

**Consequences (testable):**
- Intervention without a Provision term is rejected (notes cannot substitute).
- Intervention is visible to SENCO immediately after save.

#### FR-16: Record Pupil Response

Users who can record Interventions can record Pupil Response linked to an Intervention or dated context.

**Consequences (testable):**
- Pupil Response without a Pupil is rejected.
- Linking to a missing Intervention is rejected.

#### FR-17: No parallel-logging mandate

The product does not require the same event to be typed again into GuidelyEdu if it arrived via Connector with equivalent mapped fields.

**Consequences (testable):**
- An imported Intervention with the same external id is not duplicated on sync.
- Manual clone still possible only as a new Evidence Record (new id).

#### FR-18: Draft Evidence Record

A User can save a draft Observation/Intervention/Pupil Response and submit later.

**Consequences (testable):**
- Drafts are not included in SRE evaluation.
- Drafts are visible only to the author and SENCO of that School. `[ASSUMPTION: SENCO can see stranded drafts.]`

#### FR-19: Hybrid-appropriate capture

The Hybrid Client supports FR-14–FR-16 with the same server validations as the Web Client (realizes UJ-1).

**Consequences (testable):**
- A capture submitted from Hybrid Client is indistinguishable in the Evidence Base except for client-type on the Audit Event.
- Offline: if a queue exists, unsubmitted items are labelled not-on-server. `[ASSUMPTION: offline queue.]`

### 5.5 Evidence Base

**Description:** Each Pupil has one Evidence Base per School that accumulates mapped Evidence Records. SRE always evaluates this set, not a single form in isolation. Realizes UJ-2.

**Functional Requirements:**

#### FR-20: Chronological Evidence Base

SENCO and authorised Roles can view a Pupil’s Evidence Records in time order, filterable by type (Observation, Intervention, Pupil Response, review note, import).

**Consequences (testable):**
- Filter by Intervention hides Observations.
- Records show author Role and timestamp.

#### FR-21: Amend with history

Authors and SENCO can correct an Evidence Record; previous version remains retrievable.

**Consequences (testable):**
- Amendment creates Audit Event and a prior version.
- SRE re-runs after amendment that changes mapped fields.

#### FR-22: Review notes

SENCO can add a review note Evidence Record that is in-scope for documentation but marked as professional commentary.

**Consequences (testable):**
- Review notes are attributable and cannot be anonymous.

### 5.6 Ontology

**Description:** The Ontology is the source-of-truth knowledge model: Need, Provision, Outcome, Threshold Definitions, Relationship Mappings. Authored originally in knowledge-engineering tooling; GuidelyEdu runtime uses a versioned operational copy. Prototype includes Ontology v1 aligned to Graduated Response evidence domains. Realizes all SRE features.

**Functional Requirements:**

#### FR-23: Five Ontology domains

The operational Ontology exposes Need taxonomy, Provision taxonomy, Outcome framework, Threshold Definitions and Relationship Mappings.

**Consequences (testable):**
- Each domain has a readable catalogue for SENCO (names + identifiers), not only code constants.
- Unknown term ids on write are rejected.

#### FR-24: Map evidence into the Ontology

On submit/import, each Evidence Record is mapped to Ontology terms required for Rules (Need, Provision, evidence type, Review Cycle links where applicable).

**Consequences (testable):**
- Unmapped required fields leave the record as “unmapped” and raise a Gap rather than a silent pass.
- Mapping result is stored and visible on the Reasoning Pathway.

#### FR-25: Versioned Ontology

Ontology changes are versioned. Existing Determinations store the Ontology version used.

**Consequences (testable):**
- Publishing Ontology v2 does not rewrite historical Reasoning Pathways; they show v1.
- New SRE runs declare the version used.

### 5.7 Statutory Reasoning Engine

**Description:** The SRE is an internal deterministic service. Rules are database-backed and versioned. It evaluates Sequential Compliance (Graduated Response order), Evidential Sufficiency, Proportionality (Provision vs Need), and Outcome Progression. Outputs are transparent, consistent, auditable. It is not a predictive or diagnostic model. Realizes UJ-2.

**Functional Requirements:**

#### FR-26: Four evaluation dimensions

Every SRE run produces Determinations across Sequential Compliance, Evidential Sufficiency, Proportionality and Outcome Progression (or explicit “no applicable Rule” per dimension).

**Consequences (testable):**
- A Pupil with zero Evidence Records receives insufficient-evidence Determinations, not passes.
- Dimension results are separately queryable.

#### FR-27: Rule shape

Each Rule has a defined condition, evaluation, and Determination outcome, and belongs to a Rule Library version.

**Consequences (testable):**
- A Rule cannot be saved without condition + outcome definition.
- Disabled Rules are not executed but remain in history.

#### FR-28: Re-evaluate on new evidence

Creating, amending or importing Evidence Records re-runs applicable Rules for that Pupil.

**Consequences (testable):**
- After a new Intervention, stored Determinations timestamp updates.
- Unrelated Pupils are not re-evaluated.

#### FR-29: Determinism

The same Evidence Base + Rule Library version + Ontology version always yields the same Determinations.

**Consequences (testable):**
- Re-running SRE without data changes produces identical Determination payloads (except run id / timestamp).
- No random sampling or generative text inside the Determination payload.

#### FR-30: Subset Rule coverage (initial library)

The first build ships an initial Rule set for common documentation and threshold scenarios, not the claim of full SEND framework coverage. Uncovered scenarios return “no applicable Rule / insufficient coverage” rather than a fake pass. FR-31 expands that library inside the same build.

**Consequences (testable):**
- UI copy states coverage is partial until FR-31 Rules for the Tenant’s published Rule Library version say otherwise.
- Dimensions with no Rule are labelled uncovered, not healthy.

#### FR-31: Rule expansion for escalation and review thresholds

The Rule Library includes escalation and review-threshold Rules (business plan Phase 1 deliverable) without changing FR-26–FR-29.

**Consequences (testable):**
- New Rules appear as a new Rule Library version.
- SENCOs can be shown a changelog of Rules added.

### 5.8 Explainability, Overrides and professional judgement

**Description:** For each evaluation the User sees the Rule applied, Evidence Records considered, whether conditions were met, and the Reasoning Pathway. GuidelyEdu supports judgement; it does not replace it. Contract and in-product copy must say so. Realizes UJ-2.

**Functional Requirements:**

#### FR-32: Reasoning Pathway on every Determination

SENCO (and Roles permitted to view evaluations) can open the Reasoning Pathway for any current or historical Determination.

**Consequences (testable):**
- Pathway includes Rule id/name, version, Evidence Record ids used, pass/fail per condition, final Determination.
- Pathway is readable without engineering knowledge (plain language summary + structured facts).

#### FR-33: Human confirmation on Documentation Output

Generating a Documentation Output requires an explicit confirmation that a named User accepts it as professionally reviewed, not an automated statutory decision.

**Consequences (testable):**
- Output file/header stores confirming User, time, and a fixed disclaimer string.
- Generation without confirmation is rejected.

#### FR-34: Override

SENCO or School Leader can Override a failing or insufficient Determination with mandatory rationale to continue a Review Cycle.

**Consequences (testable):**
- Override does not delete the Determination.
- Rationale shorter than a defined minimum (product: 20 characters) is rejected. `[ASSUMPTION: 20 characters.]`
- Override is an Audit Event.

#### FR-35: No diagnostic claims

Product copy, exports and APIs do not label Determinations as diagnoses, eligibility decisions, or predicted outcomes.

**Consequences (testable):**
- A copy review list (disclaimer strings) is present on Evidence Base, School Report, Documentation Output.
- SRE payloads have type `determination`, never `diagnosis` or `prediction`.

### 5.9 Gaps and documentation status

**Description:** The platform highlights missing or weak documentation earlier and updates documentation status as evidence accumulates. Realizes UJ-1, UJ-2, UJ-3.

**Functional Requirements:**

#### FR-36: Gap list per Pupil

SENCO can view open Gaps for a Pupil derived from current Determinations.

**Consequences (testable):**
- Resolving the underlying Rule (new evidence that meets the condition) closes the Gap without manual delete.
- Manual “acknowledge” does not close a Gap unless it is an Override (FR-34).

#### FR-37: Documentation status

Each Pupil has a Documentation Status derived from Determinations, Gaps, and open Review Cycles.

**Consequences (testable):**
- Allowed values are exactly: `ready`, `gaps`, `uncovered`, `not-started`, `evaluating`.
- Status changes only via SRE/Review Cycle/job state — not free-text SENCO colouring.
- School Report counts match Pupil-level statuses (excluding or separately reporting `evaluating` as in-flight).
- `evaluating` is set when an SRE job is queued and cleared when the job completes.

### 5.10 Documentation Outputs

**Description:** Evaluated evidence is transformed into Review summaries, EHCP evidence packs, and later tribunal/inspection packs. Outputs are generated from the Evidence Base, not a blank template the SENCO fills from memory. Realizes UJ-2.

**Functional Requirements:**

#### FR-38: Review summary

SENCO can generate a review summary Documentation Output for a Pupil and Review Cycle.

**Consequences (testable):**
- Summary cites Evidence Record ids and Determination ids.
- Regeneration creates a new version; previous versions remain.

#### FR-39: EHCP evidence pack

SENCO can generate an EHCP evidence pack Documentation Output from the Evidence Base.

**Consequences (testable):**
- Pack includes Need, Provision, Outcome-mapped items present; absent domains are listed as Gaps, not omitted silently.

#### FR-40: Export-ready file

Documentation Outputs download as a portable file (PDF or equivalent) plus a structured sidecar for audit.

**Consequences (testable):**
- Download creates an Audit Event.
- File contains confirmation disclaimer from FR-33.

#### FR-41: Tribunal and inspection packs

Documentation Output types for tribunal and inspection contexts, with advanced Audit Event trace attached. Enabled per Tenant via FR-4.

**Consequences (testable):**
- When the Tenant flag is off, these types are unavailable (not silently empty).
- When on, pack generation is an Audit Event with purpose field.

### 5.11 Review Cycles

**Description:** Review Cycles (including Annual Review) organise dates, thresholds and outputs. The first build includes manual cycle management and automated Review Cycle management (FR-44). Realizes UJ-2, UJ-3.

**Functional Requirements:**

#### FR-42: Create Review Cycle

SENCO can create a Review Cycle for a Pupil with type (including Annual Review), due date, and optional EHCP link flag.

**Consequences (testable):**
- Past-due Review Cycles appear on SENCO due lists.
- Closing a cycle requires SENCO action; it does not auto-close on date alone unless FR-44 policy is configured to do so. `[ASSUMPTION: default is manual close.]`

#### FR-43: Due list

SENCO and School Leader can list Review Cycles due in a date window for their School.

**Consequences (testable):**
- Window filter 7/30/90 days works.
- Other Schools in a Trust are not listed for School-scoped Roles.

#### FR-44: Automated Review Cycle management

The product can create or roll forward Review Cycles from policy (e.g. Annual Review anniversary) when enabled for the Tenant.

**Consequences (testable):**
- When policy automation is off, only manual FR-42 cycles exist.
- When on, auto-created cycles are Audit Events with actor `system`.

### 5.12 School Report

**Description:** School-level reporting on documentation status, Gaps, and Review Cycle due dates. Phase 1 adds an academy-level reporting prototype. Not a whole-school MIS (attendance, budget, census). Realizes UJ-3.

**Functional Requirements:**

#### FR-45: School documentation report

School Leader and SENCO can open a School Report: counts of Pupils by Documentation Status, open Gaps, Review Cycles due — suitable for showing “is this School review-ready?” to a Headteacher (business plan Phase 1 academy reporting).

**Consequences (testable):**
- Figures equal the sum of in-scope Pupils (no sampling).
- No attendance, budget, or general curriculum KPIs.
- Report is generated in under 5 seconds for ≤500 Pupils (NFR bound; see §8).

#### FR-46: School Report performance bound

Acceptance of FR-45 under Pilot scale.

**Consequences (testable):**
- Same report as FR-45 meets ≤5s for ≤500 Pupils; failure is an NFR defect, not a different report product.

### 5.13 MIS Connector and Import Template

**Description:** Integration-first: interoperability over replacement; minimise duplicate entry; start without a Connector via Import Template. Connectors use secure APIs and established education hubs (Wonde, Groupcall cited illustratively). Schools control what is shared. The first build includes selective Connector configuration **and** standardised production Connectors for major MIS platforms (FR-50). Realizes UJ-4.

**Functional Requirements:**

#### FR-47: Import Template

Tenant Admin or SENCO can upload a structured Import Template for Pupils and optional historical Evidence Records, receive row-level errors, and commit valid rows.

**Consequences (testable):**
- Partial success commits valid rows and reports invalid row numbers.
- Re-upload with same Pupil key updates rather than duplicating.

#### FR-48: Connector configuration

Implementation operator / Tenant Admin can enable a Connector for a Tenant with School-controlled field sharing settings.

**Consequences (testable):**
- Disabled fields never appear in GuidelyEdu after sync.
- Connector secrets are not displayed back in UI.

#### FR-49: Sync without duplication

Connector payloads upsert by MIS key.

**Consequences (testable):**
- Two syncs of the same Pupil yield one Pupil record.
- Evidence Records from MIS carry external ids.

#### FR-50: Standardised MIS Connectors

Production connectors for major MIS platforms with repeatable onboarding (not founder-configured one-offs).

**Consequences (testable):**
- A new School in an existing Trust can enable a **supported** Connector without engineering changes.
- Unsupported MIS still works via Import Template (FR-47).
- **Pilot freeze:** Before implementing FR-48–50 beyond the adapter interface, name exactly one primary adapter family for Pilot (Wonde, Groupcall, or one direct MIS API among Arbor/SIMS/Bromcom). Until named, Import Template is the only live onboarding path; FR-50 acceptance for that Pilot is “adapter interface + one implemented primary + Import Template fallback.”

### 5.14 Trust Dashboard

**Description:** Multi-academy governance: portfolio Indicators, escalation signals, benchmarking and trend monitoring. Implemented in the first Laravel build; Tenant flag (FR-4) controls exposure. Realizes UJ-5.

**Functional Requirements:**

#### FR-51: Multi-academy Indicators

Trust SEND Lead can view Indicators across enabled Schools: Review Cycle lateness, Gap density, documentation status mix.

**Consequences (testable):**
- Default views do not dump Pupil-level narrative evidence.
- Drill-down to a School uses School Report permissions.

#### FR-52: Escalation Indicators

Trust Dashboard can flag Schools or Pupils meeting encoded escalation Rules (portfolio-level).

**Consequences (testable):**
- Flag generation is explainable (which Rule).
- Flag is not a diagnostic.

#### FR-53: Portfolio benchmarking and trends

Cross-School benchmarking, compliance trend monitoring, and escalation forecasting as described in the business plan Phase 3 capability list. `[NON-GOAL for Pilot SM validation: flag default off; SM-1–SM-5 do not require this FR.]`

**Consequences (testable):**
- When the Tenant flag is off, routes return not-available.
- When on, minimum Indicators are Review Cycle lateness rate, Gap density, Documentation Status mix (same family as FR-51), plus optional month-over-month trend of those aggregates.
- Forecasting, if present, is labelled as Indicator extrapolation, never a Pupil prognosis; stays School/Trust aggregate only.

### 5.15 Administration, Pilot toolkit, version operations

**Description:** Pilot deployment toolkit, administration, Ontology/Rule operations, and standardised deployment tooling so founder-led configuration is not required at Trust scale.

**Functional Requirements:**

#### FR-54: User and Role administration

Tenant Admin can invite/deactivate Users, change Roles, and reset access within the Tenant.

**Consequences (testable):**
- Deactivation immediately blocks API access.
- Last Tenant Admin cannot be deactivated if it would orphan the Tenant. `[ASSUMPTION]`

#### FR-55: Pilot toolkit

A Pilot Tenant can be created with a sample-or-empty cohort, Import Template download, in-product disclaimer pack, and a success-metrics export (time-to-prepare Review Cycle is a success Indicator the business plan names). `[ASSUMPTION: exact export columns defined at Pilot kickoff.]`

**Consequences (testable):**
- Pilot toolkit is documented as a checklist the implementation operator can complete without engineering.

#### FR-56: Rule and Ontology administration (operator)

A platform operator (not School Teacher) can load a new Ontology/Rule Library version into a Tenant in a controlled publish step.

**Consequences (testable):**
- Teachers cannot edit Rules.
- Publish is an Audit Event.

#### FR-57: Standardised deployment tooling

New School onboarding in a Trust follows a repeatable runbook (config, Connector, Users) rather than founder-only setup.

**Consequences (testable):**
- Documented runbook exists; completing it does not require code change.

### 5.16 Safeguarding-adjacent signals

**Description:** Structured integration with adjacent safeguarding signal frameworks and automated compliance risk alerts (business plan Phase 3 list). GuidelyEdu is not a safeguarding case-management system. Tenant flags control exposure (FR-4).

**Functional Requirements:**

#### FR-58: Safeguarding signal ingest

GuidelyEdu may receive a minimised safeguarding signal (presence/severity category, not case notes) to inform context — not to run child-protection workflow — when the Tenant flag is on. `[NON-GOAL for Pilot validation: default flag off; Pilot SMs do not require this FR.]`

**Consequences (testable):**
- Tenant flag defaults **off**.
- When off, ingest endpoints reject or no-op without storing signals.
- When on, signals are Audit Event–logged and Role-restricted to SENCO + School Leader only.
- Payload must not include free-text case notes or full safeguarding records.

#### FR-59: Compliance risk alerts

Automated alerts when documentation risk Indicators cross thresholds at School or Trust level.

**Consequences (testable):**
- Alerts cite Rules/Indicators; they do not instruct exclusion or legal action.

### 5.17 Adaptive Ontology for regulatory change

**Description:** Centralised Ontology updates as SEND and inspection frameworks change, without redesigning the product.

**Functional Requirements:**

#### FR-60: Controlled Ontology roll-forward

Platform can publish a new Ontology version to Tenants with a migration that preserves historical Determinations (FR-25).

**Consequences (testable):**
- Tenant can be on version N while another Tenant remains on N-1 during rollout.
- Diff of Rule Library is available to SENCO as a changelog.

## 6. Non-Goals (Explicit)

- Not an MIS: no attendance, census, timetable, finance, behaviour-for-all, or general gradebook.
- Not a Pupil LMS, parent portal, clinician EMR, or Local Authority EHCP casework system.
- Not probabilistic / generative “AI recommendations”, diagnostic support, or at-risk prediction of Pupils.
- Not a replacement for professional judgement, Local Authority decision-making, or tribunal outcomes.
- Not a generic safeguarding or mental-health clinical tool (safeguarding signals are adjacent context only, not the product).
- Not a public marketplace or consumer download with self-signup.
- Not implementation of the October 2025 family-care wireframe product.

## 7. First Laravel Build Scope

**Decision (2026-08-20):** The first Laravel + Vue (+ hybrid) build implements **FR-1 through FR-60** in full. Business-plan Phase 1 / 2 / 3 labels describe GTM and Tenant enablement order, not a deferred engineering backlog.

**Accepted delivery risk:** Feature flags hide Trust/governance surfaces from a Pilot Tenant; they do **not** remove engineering cost for FR-51–FR-60. Founder capital accepts implementing the full surface while validating School value first. Rejected alternative: cut Trust/safeguarding/tribunal FRs from the first codebase (retracted 2026-08-20).

### 7.1 In Scope

- **All functional requirements:** FR-1–FR-60.
- **Pilot validation spine (SM-1–SM-5):** FR-1–FR-49 School path (tenancy, capture, Evidence Base, Ontology/SRE, Gaps, Review Cycles, School Report, Import Template) plus Hybrid capture (FR-19). Trust Dashboard, tribunal packs, safeguarding ingest, and Phase-3 Indicators (FR-51–FR-53, FR-58–FR-60 as applicable) ship flag-off for Pilot Tenants.
- Web Client for all Roles; Hybrid Client for Teacher/Support Staff capture and SENCO review (FR-19) — **locked**.
- Feature flags (FR-4) so a Pilot Tenant can run School-only surfaces while Trust Dashboard, tribunal packs, safeguarding ingest, etc. remain implemented and testable.
- UK GDPR-aligned controls in §10–§12 (encryption, RBAC, logging, tenancy). Cyber Essentials, full DPIA pack, and BCP remain programme work alongside the build, not silent.

### 7.2 Out of Scope for this product (still)

- Parent/Pupil/clinician apps; marketing website; Help Centre LMS; lesson planning; whole-school MIS features.
- Native-only iOS/Android features that are not in the Web Client (Hybrid Client packages the Vue app).
- Dropping Hybrid from v1 (OQ-1 closed: Hybrid stays).

## 8. Cross-Cutting NFRs

- **NFR-1 Latency (capture):** Submitting an Observation from Web or Hybrid Client returns success or validation error within 2 seconds under Pilot load (≤100 concurrent Users per Tenant). `[ASSUMPTION]`
- **NFR-2 Latency (SRE):** SRE run for one Pupil with ≤500 Evidence Records completes within 3 seconds. `[ASSUMPTION]`
- **NFR-3 Availability:** Pilot target 99.0% monthly excluding planned windows; paid Trust Tenants 99.5%. `[ASSUMPTION: business plan did not publish SLA numbers.]`
- **NFR-4 Accessibility:** Web Client WCAG 2.2 AA for SENCO and Teacher flows. `[ASSUMPTION: 2.2 vs 2.1; business plan did not specify version.]`
- **NFR-5 Observability:** Failed SRE runs, sync failures, and 5xx rates are visible to platform operators within 5 minutes.
- **NFR-6 Localization:** Product language English (UK). US statutory terms (e.g. IEP as a primary object) are defects.
- **NFR-7 Time:** All User-facing dates in Europe/London; stored in UTC.

## 9. Platform

- **Web Client** is the complete product for all Roles.
- **Hybrid Client** is the same workflows packaged for iOS/Android, prioritising Teacher/Support Staff capture and SENCO review on phone/tablet. Trust Dashboard and Tenant Admin may remain web-primary.
- **v1 devices:** current iOS/Android plus desktop browsers (Chrome, Edge, Safari, latest two versions). `[ASSUMPTION]`
- Delivery stack is in `addendum.md` (Laravel, Vue, hybrid wrapper). The PRD does not require a particular language runtime.

## 10. Constraints and Guardrails

### 10.1 Safety

- Determinations are documentation evaluations, not clinical or eligibility decisions (FR-35).
- Overrides are mandatory when proceeding against Gaps (FR-34).
- No automated contact with Pupils.

### 10.2 Privacy

- UK GDPR and Data Protection Act 2018. Children’s data: data minimisation, purpose limitation, Tenant-controlled retention and secure deletion (business plan §3.6.2, §9).
- Encryption in transit and at rest.
- Hierarchical RBAC; no unrestricted “super search” across Trust Pupils for Teacher Roles.
- **Data residency:** UK (or explicitly UK-adequate) processing — locked with architecture AD-14.

### 10.3 Cost / capital

- Year 1 is founder-funded, non-revenue. The product must still be operable without a large ops team (founder + contractors). Feature flags (FR-4) stage Tenant exposure during Pilots; they do not defer FR implementation.

## 11. Compliance and Regulatory

GuidelyEdu design and documentation processes must be consistent with:

- UK GDPR and DPA 2018.
- SEND Code of Practice 0–25.
- Keeping Children Safe in Education (as contextual safeguarding expectation — GuidelyEdu is not the safeguarding system).
- ICO registration, DPIA support materials, data processing documentation and contract templates before first Trust procurement (business plan §9.3.1) — programme deliverables, not all in-app FRs.
- Cyber Essentials progression before Trust procurement.
- Explainability and Audit Events as accountability controls, not optional telemetry.

`[NOTE FOR PM]` KCSIE mapping: keep a short addendum list of “we store X / we never store Y” before Pilot.

## 12. Data Governance

- **Classification:** Pupil SEND evidence is special-category / highly sensitive education data. Treat as Confidential.
- **Residency:** UK (or explicitly UK-adequate) processing — locked (architecture AD-14).
- **Retention:** Configurable per Tenant only. **No product-wide numeric default** (including any “12 months after leave” example). Pilot must not write leavers’ retention behaviour until Tenant policy is configured by legal/SENCO. Product may ship a “policy not configured” block that refuses automated purge/archive.
- **Deletion:** Secure deletion process; Audit Event retained after content erasure as far as law allows.
- **Subject access / erasure:** Tenant Admin + Platform Operator runbook for SAR (not necessarily Pupil self-serve).
- **Sharing:** Connector sharing is opt-in by field. No selling of Pupil data.

## 13. Audit Trail / Decision Provenance

Every Determination, Documentation Output, Override, Connector sync and export is attributable. Historical Ontology/Rule versions remain with the Determination that used them. This is a regulated-education requirement, not debug logging. Tenant Admin cannot purge Audit Events from the product UI.

## 14. Integration and Dependencies

| Dependency | Role | First Laravel build |
|---|---|---|
| MIS (Arbor, SIMS, Bromcom, etc.) | System of record for pupils | Import Template required; standardised Connectors for supported MIS (FR-50) |
| Wonde / Groupcall (illustrative) | Connector fabric | In scope where chosen for Pilot/supported set |
| School SSO | Identity | FR-9 enablement path; provider configured per Tenant |
| Protégé (or successor) | Ontology authoring off-platform | Authoring, not runtime |
| PostgreSQL-class data store | Runtime data / Rule tables | Yes (business plan §3.5) |

GuidelyEdu must function as a standalone documentation layer if the Connector is late (UJ-4).

## 15. Rollout and Change Management

**Engineering:** First Laravel build delivers FR-1–FR-60 (see §7).

**Commercial / Tenant enablement** (business plan sequencing — flags, not missing code):

1. **Pilot / School validation:** Capture, SRE, explainability, Documentation Outputs, School Report, Import Template; Trust and advanced flags typically off.
2. **Trust entry:** Trust Dashboard, automated Review Cycles, standardised Connectors, deployment tooling enabled as contracts require.
3. **Governance scale:** Portfolio analytics, inspection/tribunal packs, safeguarding signal integration, adaptive Ontology updates exposed as Tenants mature.

Training: Role-based; Teachers capture only; SENCOs own explanation and outputs. “The platform supports, not replaces, judgement” is onboarding content, not a footer afterthought.

## 16. Stakeholders and Approvals

| Stakeholder | Role vs product | Proof they need |
|---|---|---|
| Teacher / Support Staff | Operator | Low-friction capture |
| SENCO | Operator / champion | Explainable Gaps, time to assemble Annual Review |
| School Leader | Oversight | School Report |
| Trust SEND Lead | Champion / later operator | Cross-School Indicators |
| Trust CEO / CFO / board | Buyer | Risk, cost predictability, GDPR pack |
| Founder | Product + delivery Year 1 | Pilot metrics |
| Data protection advisor | Gate | DPIA, processing records |
| SEND advisor | Gate | Ontology/Rule fidelity |

Procurement of a Trust does not start until Pilot evidence and the §9.3.1 pack exist.

## 17. Monetization

- Annual SaaS. Modelled £8 per Pupil per year; Trust portfolio discount (illustrative 15% on 8×500).
- Year 1 Pilots may be low/no cost for structured feedback (business plan).
- GuidelyEdu does not show ads. Data is not a monetization channel.

## 18. Operational Requirements

- **Support (Pilot):** Founder-led, next-working-day for blocking issues. `[ASSUMPTION]`
- **BCP / backups / incident response:** Required before first Trust procurement (business plan §9); design hooks in first build (backup-friendly store, encryption). RTO/RPO numbers are `[ASSUMPTION]` to set in architecture (propose RPO 24h Pilot, 4h paid).
- **On-call:** Not 24/7 in Year 1. `[ASSUMPTION]`

## 19. Risk and Mitigations (product)

| Risk | Product mitigation |
|---|---|
| Users think GuidelyEdu decides SEND | FR-33–FR-35, onboarding copy |
| Workload increase | Capture-once, Import Template, no parallel logging (FR-17) |
| Integration delay | UJ-4 / FR-47 |
| Partial Rule coverage over-claimed | FR-30 labelling |
| Data incident | FR-1, FR-10, encryption, minimisation |
| Founder bottleneck | FR-57; configuration over custom |
| Scope leak into MIS/LMS/AI family app | §6 Non-Goals |

## 20. Success Metrics

Pilot metrics named in the business plan (time to prepare Annual Review, completeness, less retrospective collation, confidence) are the ones that matter. Numbers below are `[ASSUMPTION]` except where the plan already set commercial targets.

**Primary**
- **SM-1:** Median SENCO time to produce an Annual Review Documentation Output in Pilot Schools falls ≥30% vs each School’s pre-Pilot baseline (measured by diary study or in-app timestamps). Validates FR-38, FR-32, FR-36.
- **SM-2:** ≥80% of Pilot SENCOs agree the Reasoning Pathway is understandable without vendor support (end-of-Pilot survey, 5-point; top-2 box). Validates FR-32, FR-35.
- **SM-3:** ≥90% of Pupils in the Pilot cohort have a non-`insufficient` Determination from an SRE run in the last 14 days of a Review Cycle, or Documentation Status `ready`/`gaps` (not `not-started`/`evaluating` alone). Validates FR-28, FR-37, FR-42.

**Secondary**
- **SM-4:** ≥70% of Teachers in Pilot log at least one Evidence Record per week they have assigned SEND Pupils. Validates FR-14–FR-16, FR-19.
- **SM-5:** Import Template or Connector used so that ≥95% of Pilot Pupils are present on day 1 of capture. Validates FR-47–FR-49.
- **SM-6 (commercial, Year 2+):** Paid conversion path per business plan (not an engineering completeness gate — all FRs are in the first build).

**Counter-metrics (do not optimize)**
- **SM-C1:** Count of Documentation Outputs generated — does not mean quality; a SENCO generating empty packs to game SM-3 is failure. Counterbalances SM-3.
- **SM-C2:** Number of Overrides — rising Overrides may mean bad Rules, not “engagement”. Counterbalances SM-2.
- **SM-C3:** Time-on-system for Teachers — GuidelyEdu should reduce admin time, not increase it. Counterbalances SM-4.
- **SM-C4:** Probabilistic model accuracy — not a metric; we do not ship that class of model.

## 21. Open Questions

**Closed for this PRD (2026-08-20):**
1. ~~Hybrid Client in first release?~~ → **Both** Web + Hybrid (locked).
5. ~~Override Roles?~~ → **SENCO and School Leader** (locked).

**Pilot entry gates (must resolve before live Pupil data / named Connector work):**
2. Real retention periods for SEND files vs GDPR storage limitation — legal sets Tenant policy; product has no numeric default (§12).
4. Which MIS / hub is the first standardised Connector (FR-50) for Pilot Schools? — name one primary before FR-48–50 beyond adapter + Import Template.

**Still open (defaults for stories; owners may revise without PRD rewrite if FR IDs stay):**
3. Teacher Pupil lists: MIS class membership vs SENCO assignment vs both? → **Default for stories: SENCO assignment; MIS class membership when Connector present (union).** Owner: Product.
6. Is Scotland/Wales/NI Ontology out of scope permanently for v1 or just deferred? → **Default: England-first Ontology only in first build.** Owner: Domain/Ontology.
7. Should Support Staff share the Teacher capture form exactly, or a reduced Intervention-only form? → **Default: same capture forms as Teacher; assigned Pupils only.** Owner: UX.
8. Success-metric instrumentation in-app vs paper baseline for SM-1? → **Default: in-app timestamps for Review Cycle pack ready; paper baseline optional at Pilot kickoff.** Owner: Pilot ops.

## 22. Assumptions Index

- Named UJ protagonists and beat order inferred from the business plan (not user-narrated).
- Classroom / Hybrid Client is the primary capture surface for Teachers.
- Parents, Pupils, clinicians, Local Authorities are not Users.
- Institution-provisioned accounts; no public signup.
- School Leader is read-mostly on Evidence Records.
- Trust Executive default is aggregate Indicators only.
- SSO matching by email; enumeration-safe login errors.
- Offline queue for Hybrid Client drafts.
- Drafts visible to School SENCO.
- Teacher empty state unless assigned Pupils.
- Observation field-level UX left to UX spec; Ontology-bound required fields.
- Documentation Status enum locked: `ready` | `gaps` | `uncovered` | `not-started` | `evaluating`.
- Review Cycles default to manual close; FR-44 automation is opt-in per Tenant.
- Override minimum rationale length 20 characters; Override Roles = SENCO + School Leader.
- Trust Executive / SAR processes are Platform Operator runbooks.
- NFR latency, availability, WCAG 2.2, browser matrix, Pilot support SLAs, RPO/RTO.
- UK (or adequate) data residency (AD-14).
- No product-wide retention numeric default until legal configures Tenant policy.
- Forecast Indicators stay aggregate.
- Safeguarding signals restricted to SENCO + School Leader; flag default off; not required for Pilot SMs.
- Laravel / Vue / hybrid wrapper are delivery choices (addendum), not business-plan mandates.
- Fast-path: no fresh market research this run; competitive claims are the business plan’s.
- England-first statutory encoding.
- Hybrid Client in the first Laravel build (OQ-1 closed).
- First standardised Connector set is the Pilot-chosen MIS/hub (OQ-4 gate); FR-50 shape covers adding more without PRD rewrite.
- SM numeric targets other than commercial Year 2/3 revenue tables.
- **Every FR (FR-1–FR-60) is in the first Laravel build** (Paul, 2026-08-20); Phase labels are GTM/enablement only.
- Teacher list default: SENCO assignment (± MIS class when Connector on).
- Support Staff shares Teacher capture forms.

## 23. ROI / Business Case (from the plan, not re-forecast)

Year 1 £0 revenue, 5 Pilot Schools. Year 2 £168,800; Year 3 £378,390; EBITDA positive Year 2 in the plan. Buyer is the Trust; School-level value must be proven first. This PRD does not independently validate those numbers; engineering success is Pilot SM-1–SM-5.
