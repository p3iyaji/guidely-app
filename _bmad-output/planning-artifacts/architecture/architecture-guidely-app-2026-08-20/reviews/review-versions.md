# Architecture Review — Version / Reality-Check Lens

**Artifact:** `ARCHITECTURE-SPINE.md`  
**Lens:** Verify every committed stack/version decision was web-researched or reality-checked (not asserted from training data); confirm named technologies still exist and fit; check greenfield starter defaults.  
**Review date:** 2026-08-20  
**Verdict:** **concerns**

---

## Summary

Major pins in the Stack table (Laravel 13 / PHP 8.3+, PostgreSQL 18.x, Vue 3.5.x, Capacitor 8.5.x, Sanctum, Protégé) **match current public sources** when re-checked on 2026-08-20. The spine’s claim “Verified Aug 2026” is therefore **content-plausible**, but the document itself **does not cite sources, URLs, or check timestamps**, so provenance of that verification cannot be audited from the artifact alone.

Under-specified or soft pins (Vite, Tailwind 3-or-4, Redis-as-default) and a monorepo layout that **diverges from Laravel `create-project` greenfield defaults** are the main process/fit concerns—not factual inventing of dead libraries.

---

## Method

Reality-checked against official / primary sources on **2026-08-20** (web search + docs fetches). Scope limited to committed technology and version claims in `ARCHITECTURE-SPINE.md` (Stack table, Consistency Conventions auth, Structural Seed, named deferred tools). Architectural ADs without version pins were out of scope for currency checks.

| Claim in spine | Source checked | Result |
|---|---|---|
| Laravel 13.x current supported line; PHP ≥8.3 | [laravel.com/docs/13.x/releases](https://laravel.com/docs/13.x/releases) | **Confirmed** — L13 released 2026-03-17; PHP 8.3–8.5; bugfixes Q3 2027; security through 2028-03-17 |
| “no LTS label — use 13.x” | Laravel support policy + endoflife.date | **Confirmed** — no LTS track since Laravel 6; uniform 18mo/2yr policy |
| Sanctum current with Laravel 13 | [laravel.com/docs/13.x/sanctum](https://laravel.com/docs/13.x/sanctum) | **Confirmed** — package/docs live; SPA + API-token modes documented |
| PostgreSQL 18.x current major; 16+ acceptable | [postgresql.org](https://www.postgresql.org/) (18.6 as of 2026-08-13) | **Confirmed** — 18 is current stable major; 19 in beta |
| Vue 3.5.x | npm `vue` / GitHub vuejs/core | **Confirmed** — latest stable **3.5.41** (2026-08-05) |
| Capacitor 8.5.x | Ionic blog + GitHub releases | **Confirmed** — **8.5.0** (2026-07-31); note: marketed as breaking minor (iOS UIScene) |
| Vite “current with Vue 3 tooling” | npm `vite` | **Under-pinned** — current is **8.2.2**; spine names no major |
| Tailwind 4.x or 3.x | [tailwindcss.com docs](https://tailwindcss.com/docs) (Vite install) | **Soft** — greenfield Vite path is **v4** (`@tailwindcss/vite`); v3 still exists but is not the current starter default |
| Protégé off-platform | protege.stanford.edu / GitHub releases | **Confirmed** — Desktop **5.6.9** (2026-03-07); project active |
| Redis queues recommended | Laravel queues docs (general practice) | **Assumption OK** — marked `[ASSUMPTION]`; still common prod recommendation |
| Structural seed `apps/api` + `apps/web` | Laravel create-project / Breeze scaffolding | **Not greenfield default** — official seed is single Laravel app + `resources/js` (or Inertia starter kits), not split `apps/` monorepo |

---

## Findings

### F1 — “Verified Aug 2026” is asserted without audit trail — **Medium (process)**

**Where:** Stack section intro: “Verified Aug 2026. Pin minors at install; majors below are the seed.”

**Issue:** No links, package registry snapshots, or “checked against X on date Y” notes. A reviewer cannot tell whether versions were looked up live or recalled. Content happens to be largely correct today, but the **claim of verification is not evidenced in-doc**.

**Recommendation:** Add a short provenance block (URLs + check date) for each pinned major, or a “Sources” subsection under Stack. Re-pin minors at install as already stated.

---

### F2 — Vite major left unpinned while ecosystem is on Vite 8 — **Medium**

**Where:** Stack — `Vite | current with Vue 3 tooling`

**Issue:** “Current” is not a seed pin. As of 2026-08-20, Vite is **8.2.x**; `laravel-vite-plugin` ^3 expects Vite ^8. A separate Vue SPA (`apps/web`) will also land on Vite 8 via `create-vite` / `@vitejs/plugin-vue`. Leaving this floating invites accidental Vite 7 scaffolds or mismatched docs.

**Recommendation:** Pin `Vite 8.x` (pin minor at install), consistent with other majors in the table.

---

### F3 — Tailwind “4.x or 3.x” understates greenfield default — **Low–Medium**

**Where:** Stack — Tailwind CSS `4.x or 3.x — [ASSUMPTION: match Vue starter…]`

**Issue:** Assumption tag is honest, but for a **new** Vue + Vite app the official Tailwind install path is **v4** (CSS-first `@import "tailwindcss"`, `@tailwindcss/vite`). Treating 3.x as an equal seed option preserves avoidable config debt (PostCSS/`tailwind.config` vs v4 `@theme`) and conflicts with DESIGN.md token work if the team later migrates.

**Recommendation:** Commit to **Tailwind 4.x** for greenfield unless DESIGN.md tooling forces v3; keep 3.x only as an explicit exception, not a co-default.

---

### F4 — Structural seed is not Laravel / Vue official greenfield layout — **Low–Medium (fit)**

**Where:** Structural Seed — `apps/api` (Laravel) + `apps/web` (Vue SPA)

**Issue:** This is a valid modular-monolith / monorepo choice aligned with AD-1 (JSON API + separate SPA + Capacitor), but it is **not** what `composer create-project laravel/laravel` or Laravel starter kits produce (single app, Vite assets under `resources/`, often Inertia). Capacitor + first-party SPA also fits Sanctum’s “separate repository SPA” model, which the spine correctly implies—but scaffolding docs and Sail defaults will not match the tree as drawn.

**Recommendation:** Mark Structural Seed as **deliberate divergence** from Laravel starter defaults; document the intended bootstrap path (e.g. Laravel API-only / Breeze API + `npm create vite@latest` Vue template + Capacitor), so implementers do not “correct” the layout back to Inertia-in-monolith.

---

### F5 — Sanctum “token/session” is correct but coarse for Hybrid Client — **Low**

**Where:** Consistency Conventions — `Auth | Sanctum token/session for SPA`

**Issue:** Laravel 13 Sanctum docs: first-party SPA prefers **cookie/session** (same site / CSRF); **API tokens** are for third-party / mobile. Capacitor Hybrid (AD-1 / AD-9) typically needs the **token** path (or carefully configured cookie domains). The spine does not invent a wrong library, but the one-liner does not encode the dual-client reality-check.

**Recommendation:** Split convention: Web SPA → Sanctum stateful session; Hybrid → Sanctum personal access / API tokens (or documented equivalent). Still “current with Laravel 13.”

---

### F6 — Capacitor 8.5.x pin is current; flag breaking-minor nature — **Info / Low**

**Where:** Stack — Capacitor 8.5.x

**Issue:** Version is accurate (8.5.0, 2026-07-31). Ionic documents 8.5 as a **breaking minor** (iOS UIScene / Xcode requirements). Pinning `8.5.x` without a migration note could surprise Hybrid work.

**Recommendation:** Keep 8.5.x; add a one-line note to follow Capacitor 8.5 migration / `npx cap migrate` at scaffold time.

---

### F7 — PostgreSQL 18 vs host 16 is correctly left open — **Pass**

**Where:** Stack + Open Question #3

**Issue:** None for currency. PG 18.6 is current stable; 16 remains a supported community major. Spine already defers host constraints.

---

### F8 — PHP / Laravel / Protégé / Redis / S3-compatible storage — **Pass (with notes)**

| Item | Status |
|---|---|
| PHP 8.3+ for Laravel 13 | Pass — matches official minimum (8.4/8.5 also supported; optional to prefer newer) |
| Laravel 13.x as current line | Pass |
| Sanctum exists & fits SPA+API | Pass |
| Protégé exists for OWL authoring | Pass — off-platform import path still coherent |
| Redis recommended `[ASSUMPTION]` | Pass — open question #2 already tracks Pilot choice |
| Object storage S3-compatible `[ASSUMPTION: provider]` | Pass — interface stable; provider deferred (AD-14 / OQ) |
| ULID/UUID API IDs | Pass — convention, not a versioned product claim |

---

## Committed decisions — research status matrix

| Decision | Marked verified? | Web-confirmed 2026-08-20? | Risk if stale |
|---|---|---|---|
| PHP 8.3+ | Implied by “Verified Aug 2026” | Yes | Low |
| Laravel 13.x | Yes (table) | Yes | Low |
| No Laravel LTS | Yes | Yes | Low |
| Sanctum | Soft (“current with L13”) | Yes | Low |
| PostgreSQL 18.x | Yes | Yes | Low |
| Vue 3.5.x | Yes | Yes | Low |
| Vite “current” | Vague | Yes as 8.x, but unpinned | Medium |
| Capacitor 8.5.x | Yes | Yes | Low (breaking minor) |
| Tailwind 4 or 3 | Assumption | v4 is greenfield default | Medium |
| Redis queues | Assumption | N/A (ops choice) | Low |
| S3-compatible storage | Assumption | Pattern still standard | Low |
| Protégé | Named only | Yes, still maintained | Low |
| `apps/api` + `apps/web` | Structural | Exists as pattern; **not** official starter | Medium (onboarding) |

---

## What would move this to **pass**

1. Cite check sources + dates under Stack (or a Sources appendix).  
2. Pin **Vite 8.x**.  
3. Prefer **Tailwind 4.x** as the greenfield default (drop equal-weight 3.x).  
4. Label Structural Seed as intentional non-default scaffolding and name the bootstrap commands.  
5. Clarify Sanctum session vs token by client type.

## What would move this to **fail**

Invented majors (e.g. non-existent Laravel/Vue lines), abandoned libraries treated as required runtime, or pins that contradict official support matrices. **None of those were found** on re-check.

---

## Verdict rationale

**concerns** — Stack majors are reality-consistent with August 2026 public sources, but verification is **not evidenced in the artifact**, Vite/Tailwind seeds are under-specified relative to current greenfield defaults, and the monorepo layout is a deliberate non-starter default that should be called out so implementers do not “fix” it back to Laravel/Inertia conventions.
