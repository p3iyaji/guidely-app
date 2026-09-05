---
name: Adversarial architecture review — GuidelyEdu spine
type: architecture-review
lens: adversary
source: ../ARCHITECTURE-SPINE.md
status: complete
created: 2026-08-20
verdict: fail
---

# Adversarial Review — Architecture Spine

**Source:** `ARCHITECTURE-SPINE.md` (read in isolation)  
**Lens:** Construct compliant-but-incompatible units; every pair found is a hole to close with a new or tightened AD.  
**Verdict:** **fail** — multiple high-severity ownership and mutation-path holes; spine is not yet a sufficient build-substrate for independent epic/feature teams.

---

## Method

1. Treat every `[ADOPTED]` / implicit AD Rule as the only shared law.
2. Invent two units (epic teams or modules) that each obey those Rules to the letter.
3. Show incompatible shared-data shapes, dual entity ownership, or conflicting mutation paths that still pass every AD.
4. Score the good-spine checklist separately.

Conventions tables and ER diagrams are **not** treated as enforceable ADs unless a Rule cites them. That is intentional: if a convention is load-bearing, it must be promoted to an AD or two units will diverge while remaining “compliant.”

---

## Unit Pair A — Capture vs Evaluation

| Unit | Scope | Obeys |
|---|---|---|
| **Unit Capture** | `Domain\Pupils` + `Domain\Evidence` + `Domain\Connectors` + Hybrid draft sync | AD-1, AD-2, AD-3, AD-6, AD-9, AD-11, AD-12 |
| **Unit Evaluation** | `Domain\Ontology` + `Domain\Sre` + `Domain\Outputs` + `Domain\Reviews` | AD-1, AD-2, AD-3, AD-4, AD-5, AD-6, AD-7, AD-10, AD-12 |

### Clash A1 — Evidence Record shape & “draft” gate (shared-data)

**Capture** implements Evidence as:

- `lifecycle: draft | submitted | amended`
- Drafts are client-local until `POST /evidence/accept` (AD-9); server commit creates `submitted` rows.
- Amendments create new rows with `supersedes_id` (append-friendly for AD-7).

**Evaluation** implements AD-6 (“Drafts never enter SRE”) against:

- `status: draft | active` on a single mutable row.
- Amends update in place; job payload is `evidence_record_id` only.
- Expects `active` = “in Evidence Base.”

Both satisfy AD-6 and AD-9. Neither AD defines the Evidence persistence model, amend semantics, or the exact predicate SRE jobs use to exclude drafts. Result: Capture’s append model never flips Evaluation’s `status`; Evaluation’s in-place amends break Capture’s Audit story and Hybrid “server wins” conflict payloads.

**Hole → AD needed:** Canonical Evidence Record state machine (states, transitions, amend = new version vs mutate), and the exact SRE enqueue eligibility predicate.

### Clash A2 — Who owns Gap (two owners of one entity)

ER diagram: `PUPIL ||--o{ GAP : derived`. Capability map: Gaps live under `Domain\Sre` (AD-4, AD-6). No Rule assigns write ownership of `gaps`.

**Evaluation** materializes `gaps` rows inside `SreReevaluatePupil` (deterministic snapshot of uncovered requirements).

**Capture / Reporting consumers** (still AD-compliant if Reporting is a third reader, or Capture builds “cohort readiness” for SENCO UX) treat Gap as a **read-model computed from Determinations + Ontology** on query, because AD-4 only requires SRE outputs to be Determinations + Reasoning Pathways — Gaps are not named in AD-4’s Rule.

Two Gap universes: durable rows vs ephemeral projection. Status pills (`gaps` vs `ready`) disagree for the same Pupil under identical Evidence.

**Hole → AD needed:** Single owner of Gap persistence vs derivation; whether Gap is an SRE write artefact, a projection table, or API-only; who may insert/update/delete.

### Clash A3 — Determination mutation: Override vs re-eval (conflicting state-mutation paths)

Capability map already splits **Overrides / disclaimers** across `Domain\Sre + Outputs` — an ownership smell the spine left open.

**Evaluation-SRE** (AD-4 purity): Override is a **separate record** that masks Determination display; re-eval jobs always overwrite Determination rows from pure evaluation; Overrides survive as overlays.

**Evaluation-Outputs** (AD-10): Override is confirmed human acceptance of an Output pack that **embeds and freezes** Determination citations; “Override” endpoint updates Determination fields (`overridden_at`, `override_rationale`) so packs cite the mutated Determination.

Both can claim AD-4 (evaluator stays pure) and AD-10 (human confirmation on generation). Neither AD forbids mutating stored Determinations after evaluation, nor requires Overrides to be a first-class entity with a single write API. Capture’s Evidence-triggered re-eval (AD-6) then either:

- wipes Output-side override fields, or
- leaves overlay Overrides invisible to Output citation checksums.

**Hole → AD needed:** Determination immutability after write (except supersession by new evaluation version); Override as owned entity + mutation path; interaction with AD-6 re-eval (preserve, invalidate, or re-apply overlays).

### Clash A4 — Review Cycle vs Evidence as SRE trigger (conflicting mutation paths)

AD-6 binds re-eval only to Evidence create/amend/import. Capability map binds Review Cycles to `Domain\Reviews` governed by **AD-6** without stating what Review mutations enqueue.

**Unit Reviews** enqueues `SreReevaluatePupil` on Review Cycle open/close (believes AD-6 “documentation status may show evaluating” applies to review-driven workflows).

**Unit Evidence** is the only legal enqueue source; Reviews only read latest Determinations when generating Outputs (AD-10).

Same Pupil, same Evidence Base, different job trails and “evaluating” UX depending on which unit shipped first. Audit Events (AD-7) diverge: one emits `sre.reeval.enqueued` from Reviews, the other only from Evidence.

**Hole → AD needed:** Exhaustive list of domain events that may enqueue SRE; whether Review Cycle transitions are triggers; job idempotency key (pupil + ontology/rule versions + evidence snapshot hash).

### Clash A5 — Ontology/Rule publish vs Determination pinning (shared-data / race)

AD-5: Determinations store Ontology + Rule Library versions; publishing does not mutate past Determinations. No Rule defines publish transaction boundaries or what “current” means for AD-6 jobs.

**Ontology unit:** Publish = insert new version row + flip Tenant `current_ontology_version_id`.

**SRE unit:** Job reads versions from the Evidence event payload captured at enqueue time.

Vs alternate compliant pair: job always reads Tenant “current” at execution time. Under AD-5 both are valid; under concurrent publish + Evidence write, Determinations pin different versions for the same logical edit, and “same inputs → same outputs” (AD-4) is vacuously true but operationally non-repeatable across retries.

**Hole → AD needed:** Version pin at enqueue vs execute; publish atomicity; whether in-flight jobs cancel/requeue on publish.

---

## Unit Pair B — Reporting/Trust vs Outputs/Statutory packs

| Unit | Scope | Obeys |
|---|---|---|
| **Unit Trust** | `Domain\Reporting` + `Domain\Tenancy` feature flags | AD-2, AD-3, AD-7, AD-8, AD-14 |
| **Unit Statutory** | `Domain\Outputs` + `Domain\Sre` read path + object storage | AD-2, AD-3, AD-5, AD-7, AD-10, AD-14 |

### Clash B1 — Aggregate vs citation truth (shared-data)

**Trust** builds School Report / Trust Dashboard aggregates from live `determinations` + computed coverage ratios. AD-8 only requires disabled flags → not-available (not empty perfect). Enabled path shape is unspecified.

**Statutory** freezes citations into Output metadata + checksum (AD-10). Dashboard “% ready” uses post-Override Determination fields; packs cite pre-Override snapshots (or the reverse).

No AD requires Reporting to read the same snapshot/citation model Outputs persist. Multi-School Trust rollups can disagree with per-Pupil pack contents while both pass AD-2/8/10.

**Hole → AD needed:** Reporting must consume defined read models (e.g. Determination projections or Output-accepted snapshots); ban ad-hoc re-derivation that ignores Overrides/versions.

### Clash B2 — Feature-flag semantics (conflicting exposure paths)

AD-8: flags gate **exposure**, not existence; disabled → explicit not-available.

**Trust:** Flag checked in Reporting controllers; underlying SRE/aggregate jobs still run; API returns `503/404` style not-available with `code: FEATURE_DISABLED`.

**Statutory:** Flag checked before Output generation only; Determination APIs remain fully exposed because AD-8 examples emphasize Trust Dashboard / tribunal packs / safeguarding — not core Determinations.

Both obey the letter. Product surfaces then disagree on whether tribunal-adjacent Determination detail is “exposed.” Vue (AD-1/13) can hide nav while API still serves sensitive aggregates — AD-3 covers authz but not flag semantics on every related endpoint family.

**Hole → AD needed:** Flag → endpoint/job matrix; whether disabled features may compute in background; required error contract for all gated routes.

### Clash B3 — School / Tenant / Trust scope in shared keys (two owners)

ER: `TENANT ||--o{ SCHOOL`, `SCHOOL ||--o{ PUPIL`. AD-2: every Tenant-owned row has `tenant_id`; queries default to current Tenant.

**Trust** introduces `trust_id` (or parent Tenant) and aggregate queries `WHERE trust_id = ?` across Schools — still “Tenant-owned” if Trust ≡ Tenant.

**Statutory / Pupils** treat Tenant = School MAT leaf and scope only `tenant_id` + `school_id`, with Trust Dashboard as Reporting fantasy until flags on.

No AD defines the Tenant vs School vs Trust identity model or which module owns School writes (Tenancy vs Pupils cohort admin). Cross-School Pupil move and report rollups invent incompatible foreign keys (`school_id` required vs optional; Trust as Tenant vs group table).

**Hole → AD needed:** Canonical tenancy graph (Tenant, School, Trust/group); ownership of School; allowed cross-School read paths for Reporting under AD-2.

---

## Additional holes (single-AD gaps that enable more pairs)

| ID | Hole | Compliant divergence |
|---|---|---|
| H1 | Status pills convention (`ready \| gaps \| uncovered \| not-started`) vs AD-6 `"evaluating"` | Capture adds `evaluating` to API; Evaluation maps evaluating → `not-started` or `gaps`. Convention is not an AD. |
| H2 | Dual ownership line in capability map: Overrides → `Sre + Outputs` | See Clash A3 — spine documents the fork instead of closing it. |
| H3 | AD-11 Connector upsert by MIS key vs AD-9 server-wins drafts | Connectors upsert Pupil by MIS key; Hybrid creates Pupil without MIS key then syncs. No AD on identity merge keys or conflict resource shape. |
| H4 | AD-7 append-only Audit vs soft-delete of business data | Evidence soft-delete emits Audit; SRE job may delete Gap rows (hard) as “derived.” No AD on cascading derived-data lifecycle. |
| H5 | AD-1 “versioned JSON API” without versioning rules | Capture ships `/api/v1/evidence`; Evaluation ships resource embeds under `/api/v1/pupils/{id}?include=determinations` with incompatible Determination JSON. |
| H6 | Consistency Conventions (errors, IDs, logging) not ADs | Units pick ULID vs UUID, different 422 shapes; still AD-compliant. |
| H7 | No module DB ownership / dependency rule beyond AD-12 namespaces | Evaluation writes `pupils.last_evaluated_at`; Capture owns Pupils table — silent shared-table coupling. |
| H8 | Deferred OpenAPI / shared types | Explicitly postpones the contract that would catch A1–B1; Deferred used to hide cross-unit schema law. |

---

## Good-spine checklist

| Check | Result | Notes |
|---|---|---|
| **Enforceable Rules** | **Weak** | AD-1–14 mostly have Prevents + Rule, good. Several Rules are principle-level (AD-8 exposure, AD-5 versioning, AD-6 enqueue) without state machines, owners, or schemas. Load-bearing Conventions and ER relationships are **not** Rules — unenforceable in review. |
| **Deferred not hiding divergences** | **Fail** | OpenAPI/shared types deferred until “endpoints stabilize” — exactly when Capture vs Evaluation already diverged. Retention deferred without an AD that soft-delete/cascade/derived data must honor a single policy interface. Platform-operator control plane deferred without defining who publishes Ontology versions in-process (AD-5 assumes publish exists). |
| **Operational envelope covered** | **Partial** | AD-14 residency + env diagram cover UK primary data plane. Missing ADs: queue driver failure modes / at-least-once vs exactly-once for SRE jobs; job idempotency; poison Evidence; backup/restore Tenant isolation; worker scaling vs NFR latency; object-storage CMEK (open Q only); correlating Audit with async jobs. NFR-1/2 cited by AD-6 but not operationalized. |

---

## Recommended AD closures (priority)

1. **AD-Entity Ownership** — Each ER entity has exactly one write-owner module; dual map entries (Overrides) forbidden; cross-module updates only via published domain commands/events.
2. **AD-Evidence State Machine** — States, amend semantics, draft exclusion predicate, sync conflict resource (ties AD-6, AD-9, AD-11).
3. **AD-Determination Immutability & Overrides** — Evaluations append/supersede; Overrides owned by one module; re-eval interaction defined.
4. **AD-SRE Trigger Catalog & Idempotency** — Closed set of enqueue causes; pin Ontology/Rule versions at enqueue; idempotency key.
5. **AD-Gap & Reporting Read Models** — Gap ownership; Reporting may only read approved projections/snapshots compatible with Output citations.
6. **AD-Tenancy Graph** — Tenant / School / Trust; AD-2 scope rules for rollups.
7. **AD-API Contract Law** — Promote versioning, error envelope, status enum (including `evaluating`) from Conventions → Rules; require shared contract artefact before multi-team build (tighten Deferred OpenAPI).
8. **AD-Async Ops Envelope** — Idempotent jobs, retry/poison, Audit correlation for workers (close NFR/ops gap).

---

## Verdict rationale

The spine correctly chooses modular monolith, single API, tenant isolation, pure SRE, and async re-eval — but **stops at principles**. Two epic-sized units can implement every AD to the letter and still ship incompatible Evidence shapes, dual Gap/Override ownership, and divergent SRE mutation paths. The capability map’s `Sre + Outputs` Override line and the `evaluating` vs status-pill inconsistency are smoking guns. Until ownership, state machines, trigger catalogs, and contract law are ADs, this draft is **not** a safe parallel-build substrate.

**Verdict: fail**
