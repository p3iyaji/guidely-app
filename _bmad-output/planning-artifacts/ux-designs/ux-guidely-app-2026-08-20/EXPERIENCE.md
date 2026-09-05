---
name: GuidelyEdu
status: final
created: 2026-08-20
updated: 2026-08-20
sources:
  - ../../prds/prd-guidely-app-2026-08-16/prd.md
  - ../../prds/prd-guidely-app-2026-08-16/addendum.md
  - DESIGN.md
---

# GuidelyEdu — Experience Spine

> Product IA and behaviour from the PRD. Visual tokens from `DESIGN.md` (wireframe style). Spines win over any wireframe screen layout that contradicts the PRD.

## Foundation

**Form-factor:** Multi-surface — **Web Client** (complete for all Roles) + **Hybrid Client** (same Vue SPA; Teacher/Support Staff capture and SENCO review first). `[ASSUMPTION: Capacitor shell per addendum.]`

**UI system:** Vue 3 SPA + Tailwind tokens mapped to `DESIGN.md`. No separate native UI kit.

**Product:** Complementary SEND interpretation/documentation layer beside MIS. Deterministic SRE; never diagnostic/generative AI surfaces.

**Tenancy:** School or Trust Tenant; feature flags hide Trust/advanced surfaces without removing them from the build (PRD FR-4).

## Information Architecture

Authenticated shell (from wireframe chrome, **nav labels from PRD**):

| Surface | Primary Roles | Purpose | PRD |
|---|---|---|---|
| Sign in | All | Credentials (SSO when configured) | FR-8, FR-9 |
| Role home (Dashboard) | Per Role | KPI + due work + shortcuts | — |
| My Pupils / cohort | Teacher, Support Staff, SENCO | Assigned/School Pupil list | FR-11–13 |
| Capture Observation | Teacher, Support Staff | Structured Observation | FR-14 |
| Capture Intervention | Teacher, Support Staff, SENCO | Provision-linked Intervention | FR-15 |
| Capture Pupil Response | Teacher, Support Staff, SENCO | Response linked to Intervention/context | FR-16 |
| Drafts | Author + School SENCO | Unsubmitted drafts | FR-18 |
| Pupil Evidence Base | Scoped Roles | Chronological Evidence Records + status | FR-20–22 |
| Determinations & Gaps | SENCO (+ School Leader read) | Four dimensions, Gap list, status | FR-26–37 |
| Reasoning Pathway | SENCO (+ permitted) | Rule, evidence, pathway | FR-32 |
| Override | SENCO, School Leader | Proceed with rationale | FR-34 |
| Review Cycles | SENCO | Create, due list, close; automation when flagged | FR-42–44 |
| Documentation Outputs | SENCO | Review summary, EHCP pack, tribunal/inspection when flagged | FR-38–41, FR-33 |
| School Report | SENCO, School Leader | Documentation readiness counts | FR-45–46 |
| Import Template | SENCO, Tenant Admin | Upload Pupils/evidence | FR-47 |
| Connectors | Tenant Admin / operator | Field sharing, sync | FR-48–50 |
| Trust Dashboard | Trust SEND Lead, Trust Executive | Multi-School Indicators, escalations, trends | FR-51–53 |
| Users & Roles | Tenant Admin | Provision, deactivate | FR-5, FR-54 |
| Pilot toolkit | Operator / Tenant Admin | Checklist, templates, metrics export | FR-55 |
| Ontology / Rule publish | Platform operator | Version publish | FR-56, FR-60 |
| Compliance risk alerts | SENCO / Trust (when flagged) | Indicator-threshold alerts | FR-59 |
| Safeguarding signal (context) | SENCO, School Leader (when flagged) | Minimised signal display — not casework | FR-58 |

**Out of IA (explicit):** Parent portal, student LMS, clinician EMR, Local Authority ops, marketing site, lesson planning, attendance/budget MIS.

**Sidebar by Role** `[ASSUMPTION: exact order]`:

- **Teacher / Support Staff:** Dashboard · My Pupils · Capture · Drafts · Messages(none in PRD — omit) · Settings  
- **SENCO:** Dashboard · Pupils · Review Cycles · Gaps · Outputs · School Report · Import · Settings  
- **School Leader:** Dashboard · School Report · Review Cycles (read) · Settings  
- **Trust SEND Lead:** Trust Dashboard · Schools · Alerts · Settings  
- **Tenant Admin:** Users · Schools · Connectors · Feature flags · Pilot toolkit · Settings  

Top bar search: Pupils and Review Cycles by name/id within scope — not free-text across Trust for Teachers.

→ Composition reference: [`mockups/teacher-capture.html`](mockups/teacher-capture.html), [`mockups/senco-evidence-base.html`](mockups/senco-evidence-base.html), [`mockups/school-report.html`](mockups/school-report.html). Spine wins on conflict.

**Mock coverage:** those three surfaces are mocked; all other IA rows are spine-only for the PO pack.

## Voice and Tone

Microcopy. Brand posture in `DESIGN.md`.

| Do | Don't |
|---|---|
| “Documentation status: Gaps — 2 open” | “AI found risks in this child” |
| “Determination: evidential sufficiency — not met” | “Diagnosis incomplete” / confidence % |
| “This output supports professional judgement; it is not a statutory decision.” | “Auto-approved for EHCP” |
| “Draft — not yet on the Evidence Base” | Silent offline save that looks live |
| “No applicable Rule for this dimension (coverage)” | Green “healthy” with no Rule |
| UK Glossary: SENCO, EHCP, Graduated Response | IEP, SENDCO, client/patient |

Always show the FR-35 disclaimer near Determinations and Documentation Outputs.

## Component Patterns

Behavioral; visuals in `DESIGN.md`.

| Pattern | Behaviour |
|---|---|
| **KPI row** | 3–4 cards; number is live count; click drills to filtered list |
| **Pupil row** | Name, year, documentation status pill, next Review Cycle date; opens Evidence Base |
| **Capture form** | Required Ontology-bound fields; validate on submit; success returns to Evidence Base with new row highlighted |
| **Status pill** | Enumerated: `ready` · `gaps` · `uncovered` · `not-started` (locked 2026-08-20) |
| **Gap list** | Each Gap links to Reasoning Pathway; closed only by meeting Rule or Override |
| **Reasoning Pathway** | Expandable: Rule id/name/version → conditions → Evidence Record links → Determination |
| **Confirm output dialog** | Checkbox + named User; blocked without confirmation (FR-33) |
| **Override dialog** | Mandatory rationale (≥20 chars); keeps Determination history |
| **Import results** | Partial success table: committed vs row errors |
| **Feature-flagged empty** | “Not available for this Tenant” — never empty chart implying zero risk |

## State Patterns

| State | Treatment |
|---|---|
| Loading | Skeleton cards matching layout (wireframe density) |
| Empty Pupils | “No Pupils in your list.” SENCO: CTA Import Template / add Pupil |
| Empty Evidence Base | “No Evidence Records yet.” Teacher: Capture CTA |
| Uncovered Rule dimension | Label **Uncovered**, not success |
| Offline Hybrid draft | Banner: “Saved on this device — not on the Evidence Base until you reconnect” |
| Sync conflict | Server wins Pupil identity; draft retained for author |
| Permission denied | Hide nav item; deep link → “You don’t have access” |
| SRE running | Inline spinner on Pupil status; no blocking full-page unless &gt;3s |
| Flag off | Not-available page with Tenant Admin contact hint |
| Danger actions (export all / deactivate last admin) | Confirm dialog; never one-click |

## Interaction Primitives

- **Primary:** pointer/touch. Keyboard: visible focus `{colors.focus-ring}`; Tab order = reading order.
- **Hybrid:** large tap targets on Capture (≥44px); bottom sheet for Pupil picker `[ASSUMPTION]`.
- **Banned:** hover-only actions on mobile; generative “recommend intervention” panels; confidence meters; diagnostic language.
- **Modals:** one level deep (confirm / override / pathway detail).

## Accessibility Floor

- WCAG 2.2 AA (PRD NFR-4).
- Status never colour-only — pill text + icon.
- Screen reader: announce surface on navigate (“Evidence Base, {Pupil name}”).
- Reasoning Pathway fully readable without colour; headings hierarchy h1→h3.
- Forms: associated labels; errors linked with `aria-describedby`.
- Reduced motion: respect `prefers-reduced-motion` for skeletons only (no decorative motion required).

## Responsive & Platform

| Surface priority on Hybrid | Why |
|---|---|
| Capture + My Pupils + Drafts | UJ-1 classroom |
| Evidence Base + Gaps (SENCO) | UJ-2 on tablet |
| School Report / Trust / Admin | Web-primary; usable but dense on phone |

Europe/London dates; English (UK).

## Inspiration & Anti-patterns

- **Lifted from wireframes:** purple top bar, sidebar, white KPI cards, soft shadows, outline icons, status pills, full-width card CTAs.
- **Rejected from wireframes:** parent assessments, student LMS, clinician caseload, LA dashboard as core nav, IEP management naming, EduConnect branding, generative AI recommendation grids.
- **Rejected elsewhere:** dark-mode-first EdTech; purple-on-white marketing gradients inside operational views; “confidence score” UX.

## Key Flows

Mirror PRD UJ-1…UJ-5. Persona names and climax beats preserved.

### Flow 1 — UJ-1 James logs Intervention (Teacher, Hybrid or Web)

1. James signs in → Teacher Dashboard.
2. Opens **My Pupils** → selects Pupil.
3. **Capture Intervention** — Provision term required, session context, notes.
4. Submits → SRE re-evaluates → documentation status may update.
5. **Climax:** Evidence Base shows the new Intervention in time order; he does not keep a spreadsheet copy.
6. Failure: offline → draft banner; reconnect submits; Audit Event notes client type.

### Flow 2 — UJ-2 Aisha prepares Annual Review (SENCO, Web)

1. SENCO Dashboard → **Review Cycles** due list → opens Pupil.
2. Reads **Gaps** and four **Determinations**; opens **Reasoning Pathway** on a failing Rule.
3. Requests missing capture or adds review note; or **Override** with rationale.
4. **Generate** review summary / EHCP pack → confirmation dialog (FR-33).
5. **Climax:** Downloadable Documentation Output cites Evidence Record and Determination ids; disclaimer visible.
6. Failure: generation without confirm blocked; toast explains.

### Flow 3 — UJ-3 Priya inspection-readiness (School Leader, Web)

1. Sign in → School Leader Dashboard / **School Report**.
2. Sees counts by documentation status, open Gaps, Review Cycles due — no attendance/budget.
3. **Climax:** Knows whether the School is review-ready this month; drills to list, does not edit Evidence Records.
4. Failure: empty Tenant → “No Pilot data yet.”

### Flow 4 — UJ-4 Import before Connector (SENCO / Admin)

1. Connectors not authorised → **Import Template** download.
2. Upload → row errors + partial commit.
3. Teachers see Pupils same day.
4. **Climax:** Capture works without MIS; later Connector upserts by MIS key without duplicates.
5. Failure: all rows invalid → nothing committed; clear error file.

### Flow 5 — UJ-5 Daniel Trust oversight (Trust SEND Lead)

1. Trust Dashboard enabled → Indicators across Schools.
2. Escalation flag cites Rule; drill to School Report — not raw Pupil narratives by default.
3. **Climax:** Outlier School identified for support before board/inspection surprise.
4. Failure: flag off → not-available, not a zeroed “perfect” chart.

## Open Questions (UX)

1. Supply SVG / licensed logo to replace interim script wordmark.
2. Whether additional HTML mocks are needed before build (Trust Dashboard, Import results, Override dialog) — currently spine-only.

## Locked decisions (finalize 2026-08-20)

- Status pills: `ready` | `gaps` | `uncovered` | `not-started`
- Emerald secondary CTA retained; primary purple for primary actions
- Hybrid Teacher: bottom nav (Home, Pupils, Capture, Drafts, More)
- School Leader: School Report primary; Reasoning Pathway read-only on drill-down
- Validation lenses skipped on finalize
- Key mocks: teacher-capture, senco-evidence-base, school-report; remaining IA spine-only

## Assumptions Index

- Vue 3 + Tailwind; Capacitor hybrid.
- Light mode only v1.
- Wireframe shell kept; wireframe product IA discarded.
- Sidebar order by Role as tabled.
- Search scoped to Role.
- No in-app messaging (not in PRD).
- Brand script temporary until SVG.
- Emerald secondary retained for secondary CTA only.
