# PRD Quality Review — GuidelyEdu (prd-guidely-app-2026-08-16)

## Disposition (2026-08-20 finalize)

Critical/high findings from the draft review below were addressed in `prd.md` / `addendum.md`: Pilot entry gates (§0); full-build risk accepted (§7); retention numeric default removed (§12); FR-50 Pilot freeze; Hybrid/OQ-1 and Override/OQ-5 closed; Documentation Status enum locked; Platform Operator glossaried; FR-45/46 clarified; FR-53/FR-58 Pilot NON-GOAL notes. Remaining OQs 2/4 are gates for live data / Connector impl, not blockers for epics. Status: **final**.

---

## Overall verdict (pre-fix draft review)
This PRD has a real thesis — MIS stores; GuidelyEdu *interprets* against SEND Code of Practice via a deterministic SRE — and the FR spine, UJs, Non-Goals, and counter-metrics largely serve it. Decision-makers can see what was chosen (full FR-1–FR-60 first Laravel build; Phase labels as GTM only) and what was rejected (wireframe product, generative AI, parent/pupil apps). What is at risk is treating draft status as build-ready while retention defaults, first Connector, Teacher list source, and several enumerations remain open under children’s special-category data and Year-1 founder capital.

## Decision-readiness — strong

Trade-offs are named as decisions, not smoothed: complementary-to-MIS not replacement (§1, §6); Determinations never diagnoses (§1, FR-35); Hybrid + full FR set in first build with flags for Tenant exposure (§0 build-scope decision, §7, addendum §1/§7). Rejected paths live in the addendum (Inertia-only UI, React Native rewrite, Phase-deferred engineering, family-care wireframe). Open Questions (§21) are genuinely open — retention, MIS/hub for FR-50, Teacher list source — not rhetorical. `[NOTE FOR PM]` hits real tensions (OQ-1 hybrid confirm §7.2; KCSIE store/never-store list §11). Someone pushing “just add AI recommendations” or “replace SIMS” finds the objection pre-answered in Non-Goals and Safety.

### Findings
- **high** Full-build vs Year-1 capital tension under-litigated (§7, §10.3, §19) — The 2026-08-20 decision to ship FR-1–FR-60 is explicit, but cost/ops trade-offs are a single founder-funded paragraph plus “Founder bottleneck → FR-57.” A decision-maker cannot see what delivery risk was accepted vs a Pilot-spine cut. *Fix:* Add a short Decision box: estimated build surface (Pilot spine vs Trust/governance FRs), what flags do *not* buy you (engineering still ships FR-58–60), and explicit accept/reject of that risk.
- **medium** Default-answered OQs still block Pilot legal/ops (§21 items 1–2, 4–5) — Hybrid, Override Roles, and Connector defaults are stated, but retention (OQ-2) and first Connector (OQ-4) are not. *Fix:* Promote OQ-2 and OQ-4 to gate criteria before Pilot data / Connector work starts; leave the rest as defaults with owners.

## Substance over theater — strong

Vision (§1) fails the swap test: Ontology + SRE + explainable Graduated Response evaluation is product-specific, not generic EdTech. Personas are few and load-bearing — James/Aisha/Priya/Daniel map to UJ-1–5 and concrete FRs, not decorative casts. NFRs carry product-specific bounds (NFR-1 2s capture, NFR-2 3s SRE ≤500 records, NFR-3 Pilot 99.0%, NFR-4 WCAG 2.2 AA) rather than “must be scalable.” Differentiation is earned from Why Now + thesis (MIS organises; tools do not encode Graduated Response). Monetization (§17) and ROI (§23) correctly refuse to re-forecast the plan.

### Findings
- **medium** Governance/safeguarding FRs read plan-list thin (FR-53, FR-58–FR-59, §5.14–5.16) — “Benchmarking,” “escalation forecasting,” and “minimised safeguarding signal” inherit business-plan Phase 3 wording with consequences that mostly restate flag on/off and labelling. They do not drive Pilot SMs. *Fix:* Either thicken testable outcomes (what Indicator formulas, what signal schema fields) or mark them `[NON-GOAL for Pilot validation]` while keeping them in-codebase per the full-build decision.
- **low** Emotional JTBD line is soft but still specific (§3.1) — Fear of incomplete trail vs black-box decision is on-thesis; no fix required unless trimming JTBD for brevity.

## Strategic coherence — strong

The stated bet (§1) is narrow and repeated consistently: earlier Gap detection, less last-minute collation, defendable Documentation Outputs; professional judgement stays human. Feature groups follow that arc (tenancy → capture → Evidence Base → Ontology → SRE → explainability → Gaps/outputs → School/Trust oversight). Success Metrics validate the thesis — SM-1 time-to-Annual-Review pack, SM-2 Reasoning Pathway understandability — not vanity DAU. Counter-metrics SM-C1–C4 are unusually honest (empty packs gaming SM-3; Override spikes as Rule failure). Commercial sequencing via FR-4 flags matches “prove School value then Trust buyer” (§15, §23) without pretending Phase 3 FRs are absent from the build.

### Findings
- **medium** Full FR set strains Pilot-first thesis (§0, §7 vs §20 SM-1–SM-5) — Engineering completeness of Trust forecasting and safeguarding ingest does not serve Pilot SMs; flags hide surface but not implementation cost. Coherence holds commercially; the bet risks dilution of attention. *Fix:* In §7.1, name a “Pilot validation spine” (e.g. FR-1–FR-49 minus Trust/safeguarding) as the SM-gate surface vs “implemented but flag-off” remainder.
- **low** SM-3 is slightly activity-shaped (§20) — ≥90% Pupils with a recent SRE run can be gamed by empty runs; SM-C1 partially counters Documentation Outputs but not SRE spam. *Fix:* Tie SM-3 to non-insufficient Determinations or Review Cycle readiness status (FR-37).

## Done-ness clarity — adequate

Most FRs carry explicit testable consequences (isolation by ID enumeration FR-1; draft exclusion from SRE FR-18; determinism FR-29; Override min length FR-34). Vague “user-friendly / reasonable” language is largely absent. UJ edge cases point at FRs (UJ-2 → FR-32; UJ-4 → FR-47/48). Gaps: several FRs defer enumerations or supported sets; a few are runbook/process “done”; Phase-3-shaped FRs under-specify outcomes.

### Findings
- **high** FR-50 supported MIS set unresolved (FR-50, OQ-4, addendum §5) — Consequence “new School enables supported Connector without engineering” is untestable until the Pilot set is named. *Fix:* Freeze Pilot Connector set (even one hub/MIS) in PRD or addendum before storying FR-48–50.
- **high** Retention default flagged wrong but still in contract prose (§12, Assumptions Index) — “12 months after Pupil leaves” is written then immediately disclaimed as likely wrong for education records. Engineers could still implement the bad default. *Fix:* Replace with “no product default until legal sets Tenant policy; block Pilot write of leavers’ retention until configured.”
- **medium** FR-53 / FR-58 outcome thinness (§5.14–5.16) — Same as substance finding; story writers will invent Indicator math and signal schemas. *Fix:* Add minimum Indicator definitions and signal field contract, or defer acceptance to a companion spec with FR IDs frozen.
- **medium** FR-45 vs FR-46 near-duplicate (§5.12) — Both are School Report / academy review-readiness; FR-46’s only unique bite is the ≤500 Pupils / 5s bound (also pointed at §8). *Fix:* Merge into one FR or make FR-46 solely the NFR-bound performance acceptance for FR-45.
- **medium** FR-57 is process-done, not product-done (§5.15) — “Documented runbook exists; completing it does not require code change” is a docs gate, weak as an FR. *Fix:* Reframe as onboarding config checklist + API/CLI steps that are automated, or move to Operational Requirements.
- **low** FR-32 “plain language summary” unbounded (FR-32) — Readable without engineering knowledge lacks a checkable bar. *Fix:* Require a fixed template fields list (Rule name, evidence ids, condition results) already partly listed; drop subjective “plain language” or add SM-2 as the acceptance proxy.

## Scope honesty — adequate

Non-Goals (§6) do real work (not MIS/LMS/EMR/LA casework; not generative AI; not Oct 2025 wireframe). `[ASSUMPTION]` tags and §22 index are thorough; retention is called out as likely wrong — rare and useful honesty. `[NON-GOAL]` / out-of-scope appear on SSO IdP pre-integration (FR-9), parent operators, marketing site. Feature flags explicitly separate Tenant exposure from codebase presence (§0, FR-4). Open-item density is high relative to a full-build green light: eight OQs, dense assumptions, two PM notes — appropriate for *draft*, blocking if treated as build-complete contract without resolving Pilot gates (retention, Connector, Teacher lists).

### Findings
- **critical** Open-item density vs full-build decision (§0, §7, §21–§22) — Rubric: high Open Questions + Assumptions on a green-light-to-build PRD is a blocker. Status is `draft`, but the build-scope decision reads like engineering go. Children’s special-category data plus unresolved retention/residency elevates this. *Fix:* Either keep status draft and list Pilot entry gates (legal retention, residency, OQ-4, DPIA materials), or resolve those gates and strip assumed-wrong defaults before architecture/stories treat the PRD as frozen.
- **medium** FR-58 “may receive” softens a sensitive boundary (§5.16, §6) — Non-Goals say not a safeguarding system, but ingest is in-build. Silent assumption risk for implementers. *Fix:* Add `[NON-GOAL for Pilot]` and require Tenant flag default off + explicit schema Non-Goal (no case notes) restated on the FR.
- **low** Parents-as-subjects assumption lightly tagged (§3.2) — Second parent bullet is bare `[ASSUMPTION]` without the England-first style clause. *Fix:* One-line assumption text in-line and in §22 (already paraphrased).

## Downstream usability — strong

Chain-top intent is explicit (§0). Glossary (§4) is large and used consistently across FRs/UJs/SMs (Evidence Base, Determination, Reasoning Pathway, Gap, Tenant). FR-1–FR-60, UJ-1–UJ-5, SM-1–SM-6 / SM-C1–C4 are contiguous and cross-linked. Each UJ names a protagonist with entry/climax/resolution. Addendum correctly holds stack, permission matrix, and SRE *how* so product FRs stay extractable. Sections largely stand alone via Glossary terms.

### Findings
- **medium** “Platform operator” not a Glossary Role (FR-56, FR-1, §14) — Appears as actor for Ontology publish and Pilot toolkit but is not in the Role set (FR-6). Story/RBAC extract will invent a role. *Fix:* Glossary + FR-6: platform operator as out-of-Tenant role with permissions, or rename to “GuidelyEdu operator” with addendum matrix row.
- **medium** Permission matrix only in addendum (FR-6, addendum §3) — FR-6 says the matrix is a product artefact; downstream UX/stories must open the addendum. Fine if intentional; easy to miss. *Fix:* One-line in §5.2: “Normative permission matrix: addendum §3” or embed a condensed matrix in the PRD.
- **low** Assumptions Index is paraphrase, not roundtrip (§22 vs inline tags) — Inline tags are short; index bullets are summaries. Not every inline phrase maps 1:1 (e.g. FR-8 enumeration). *Fix:* Number assumptions (A-1…) inline and mirror IDs in §22.

## Shape fit — strong

Multi-stakeholder B2B education product with meaningful UX correctly carries named-protagonist UJs (Teacher, SENCO, School Leader, Trust). Regulatory-adjacent constraints (§10–§13) are load-bearing, not bolted on. Chain-top (UX → architecture → stories) matches Glossary/ID discipline and addendum split for delivery stack. Not over-formalized for a single-operator tool; not under-formalized for a Trust-sold SEND documentation product. Brownfield wireframe correctly excluded (§0, §6, addendum §7).

### Findings
- **low** Hybrid Client assumption vs product contract boundary (§9, addendum §1–2) — Packaging is correctly addendum-bound; FR-19 still productizes hybrid capture. Shape is right; only OQ-1 can collapse it. No fix beyond resolving OQ-1.

## Mechanical notes

- **Glossary drift:** Minor — “Documentation Output(s)” pluralization only; “academy-level reporting” (FR-46) vs Glossary “School Report”; “platform operator” missing as noted. “Indicator extrapolation” (FR-53) not glossaried (acceptable if kept aggregate-only).
- **ID continuity:** FR-1–FR-60 contiguous; UJ-1–UJ-5 contiguous; SM-1–SM-6 and SM-C1–SM-C4 contiguous. Cross-refs checked sample (UJ-2→FR-32, UJ-4→FR-47/48, UJ-5→FR-51–53, FR-46→§8) resolve. No duplicate IDs found.
- **Assumptions Index roundtrip:** Directionally complete; not ID-linked. Inline `[ASSUMPTION]` without prose (e.g. §3.2 parents, FR-5 provisioned accounts) rely on §22 paraphrase. Retention “likely wrong” appears both §12 and §22 — good.
- **UJ protagonists:** All five UJs name James, Aisha, Priya, Aisha+implementation, Daniel with context inline.
- **Required sections for stakes:** Vision, users/UJs, Glossary, FRs with consequences, Non-Goals, NFRs, privacy/compliance, audit, integrations, success metrics + counters, open questions, assumptions — present and appropriate for high-stakes education SaaS feeding UX/architecture/stories.
