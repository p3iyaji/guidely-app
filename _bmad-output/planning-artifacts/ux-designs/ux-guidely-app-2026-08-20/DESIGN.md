---
name: GuidelyEdu
description: UK SEND statutory interpretation and documentation layer for Schools and Trusts — wireframe-derived soft SaaS visual identity on a staff-facing Laravel/Vue product.
status: final
created: 2026-08-20
updated: 2026-08-20
sources:
  - ../../prds/prd-guidely-app-2026-08-16/prd.md
  - ../../prds/prd-guidely-app-2026-08-16/addendum.md
  - imports/GuidelyEdu-Wireframes.pdf
colors:
  # Sampled / inferred from wireframe deck (style source). [ASSUMPTION: hex refined at implement time against exports.]
  canvas: '#F0F0F5'
  surface: '#FFFFFF'
  surface-muted: '#F7F7FB'
  primary: '#5B63E6'
  primary-hover: '#4A52D4'
  primary-foreground: '#FFFFFF'
  primary-soft: '#E8E9FC'
  secondary: '#10B981'
  secondary-foreground: '#FFFFFF'
  text: '#1F2937'
  text-muted: '#6B7280'
  text-inverse: '#FFFFFF'
  border: '#E5E7EB'
  border-strong: '#D1D5DB'
  success: '#10B981'
  success-soft: '#D1FAE5'
  warning: '#F59E0B'
  warning-soft: '#FEF3C7'
  danger: '#EF4444'
  danger-soft: '#FEE2E2'
  info: '#3B82F6'
  info-soft: '#DBEAFE'
  topbar: '#5B63E6'
  sidebar: '#FFFFFF'
  focus-ring: '#5B63E6'
typography:
  brand:
    fontFamily: '"Segoe Script", "Bradley Hand", "Comic Sans MS", cursive'
    fontSize: 22px
    fontWeight: '400'
    lineHeight: '1.2'
    # [ASSUMPTION: script wordmark from wireframes; replace with licensed brand font when available — never Comic Sans in production; use curated script or SVG logo.]
  display:
    fontFamily: 'Inter, "Segoe UI", system-ui, sans-serif'
    fontSize: 28px
    fontWeight: '700'
    lineHeight: '1.25'
    letterSpacing: '-0.02em'
  heading:
    fontFamily: 'Inter, "Segoe UI", system-ui, sans-serif'
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.3'
  body:
    fontFamily: 'Inter, "Segoe UI", system-ui, sans-serif'
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label:
    fontFamily: 'Inter, "Segoe UI", system-ui, sans-serif'
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1.4'
  meta:
    fontFamily: 'Inter, "Segoe UI", system-ui, sans-serif'
    fontSize: 12px
    fontWeight: '400'
    lineHeight: '1.4'
  metric:
    fontFamily: 'Inter, "Segoe UI", system-ui, sans-serif'
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.1'
rounded:
  sm: 6px
  md: 10px
  lg: 12px
  xl: 16px
  full: 9999px
  DEFAULT: 10px
spacing:
  '1': 4px
  '2': 8px
  '3': 12px
  '4': 16px
  '5': 20px
  '6': 24px
  '8': 32px
  '10': 40px
  '12': 48px
  gutter: 24px
  page: 24px
  sidebar-width: 240px
  topbar-height: 56px
components:
  button-primary:
    background: '{colors.primary}'
    foreground: '{colors.primary-foreground}'
    radius: '{rounded.md}'
  button-secondary:
    background: '{colors.secondary}'
    foreground: '{colors.secondary-foreground}'
    radius: '{rounded.md}'
  button-outline:
    background: '{colors.surface}'
    foreground: '{colors.text}'
    border: '{colors.border-strong}'
    radius: '{rounded.md}'
  card:
    background: '{colors.surface}'
    border: '{colors.border}'
    radius: '{rounded.lg}'
    shadow: '0 1px 3px rgba(31, 41, 55, 0.08)'
  topbar:
    background: '{colors.topbar}'
    foreground: '{colors.text-inverse}'
    height: '{spacing.topbar-height}'
  sidebar-item-active:
    background: '{colors.surface-muted}'
    foreground: '{colors.primary}'
  status-pill:
    radius: '{rounded.full}'
  input:
    background: '{colors.surface}'
    border: '{colors.border-strong}'
    radius: '{rounded.sm}'
  focus-ring:
    color: '{colors.focus-ring}'
---

## Brand & Style

GuidelyEdu is a **calm, institutional soft-SaaS** product for UK school and Trust staff. Visual identity is taken from the existing GuidelyEdu wireframe deck: light canvas, periwinkle/purple chrome, white cards, airy spacing, thin outline icons. The product is serious (SEND documentation, audit, statutory reasoning) but must not feel clinical, punitive, or “black box AI.”

Brand posture: **supportive infrastructure next to the MIS** — clear, explainable, low-friction. Warmth comes from soft purple primary and generous whitespace, not from consumer wellness aesthetics or emoji.

`[ASSUMPTION]` Implementation uses **Vue 3 + Tailwind CSS** (addendum: Vue SPA). Component primitives may be headless + Tailwind or a Vue library styled to these tokens; tokens in this file win.

Script wordmark “GuidelyEdu” matches the wireframes. Prefer an **SVG logo** in production; until then use `{typography.brand}` only in the top bar, never for body copy.

## Colors

| Token | Role |
|---|---|
| `{colors.primary}` / `{colors.topbar}` | Brand and app chrome — top bar, primary CTAs, active accents, links |
| `{colors.primary-soft}` | Soft fills (active sidebar tint, selected chips) |
| `{colors.secondary}` | Secondary CTA only (wireframe “Learn More” green). **Do not** use for success state alone — success uses `{colors.success}` |
| `{colors.canvas}` | App background behind cards |
| `{colors.surface}` | Cards, panels, modals |
| `{colors.text}` / `{colors.text-muted}` | Primary / secondary copy |
| Status soft pairs | Pills and banners: success / warning / danger / info |

**Do not** use primary purple to mean “Gap” or “danger.” Gaps and failing Determinations use `{colors.warning}` or `{colors.danger}` with explicit labels. Primary means brand/action, not severity.

Dark mode: **out of v1**. `[ASSUMPTION]` Light only, matching the wireframe deck.

## Typography

- **UI ramp:** Inter (or system Inter-like stack) for all product chrome and content.
- **Display / heading:** Bold for Welcome and page titles; never decorative display fonts in operational screens.
- **Metric:** Large bold numbers on KPI cards (wireframe SENCO/Educator dashboards).
- **Brand script:** Top bar logo only.
- UK English spelling in all UI strings (`organisation`, `behaviour` only if user-facing and UK-correct; prefer Glossary terms from the PRD: SENCO not SENDCO, EHCP not IEP).

## Layout & Spacing

Wireframe shell for **authenticated app**:

1. Full-width `{colors.topbar}` (~56px) with logo left, primary nav centre/left, search + notifications + avatar right.
2. White **left sidebar** (~240px) with role-specific nav; active item uses soft fill.
3. Main column on `{colors.canvas}` with `{spacing.page}` padding; content in white `{components.card}` grids.

Breakpoints `[ASSUMPTION]`:

| Breakpoint | Shell |
|---|---|
| ≥1024px | Top bar + sidebar + main |
| 768–1023px | Top bar + icon sidebar or collapsible |
| &lt;768px (Hybrid Client) | Top bar + **bottom nav** for Teacher/Support Staff (Home, Pupils, Capture, Drafts, More); SENCO uses hamburger/sheet; single-column stacks; capture flows first |

Max content width for dense tables: full main pane. School Report / Trust Dashboard may use full width. Avoid marketing two-column heroes inside the authenticated app.

## Elevation & Depth

Match wireframes: **subtle card shadow** (`0 1px 3px` soft), not multi-layer glow. No neon, no glassmorphism. Modals: slightly stronger shadow; backdrop dim ~40% black.

## Shapes

Cards `{rounded.lg}` (~12px). Buttons and inputs `{rounded.md}` / `{rounded.sm}`. Status pills `{rounded.full}`. Avatar circles `{rounded.full}`. Avoid sharp zero-radius tables; table chrome may sit inside a rounded card.

## Components

| Component | Visual contract |
|---|---|
| **Top bar** | Solid `{colors.topbar}`, white logo/nav/icons; search field light/white inset |
| **Sidebar** | White; icon + label rows; active = soft grey/purple tint |
| **Card** | White, light border optional, soft shadow, 12px radius |
| **KPI card** | Metric number + label + optional outline icon top-right |
| **Primary button** | Solid purple, white text, full-width common in card footers (wireframe pattern) |
| **Outline quick-action** | White fill, grey border, icon + label (SENCO quick actions) |
| **Status pill** | Soft bg + matching text for `ready` · `gaps` · `uncovered` · `not-started` |
| **List row** | Name + meta + chevron; divider lines |
| **Progress bar** | Track muted; fill primary or status colour by meaning |
| **Reasoning Pathway panel** | Card with clear section headers; never styled as a “score” or diagnosis badge |
| **Disclaimer banner** | Muted info soft fill; always visible on Evidence Base / Outputs |

Icons: thin stroke outline (Heroicons/Lucide-like), consistent 20–24px in chrome.

## Do's and Don'ts

| Do | Don't |
|---|---|
| Reuse wireframe purple top bar + sidebar + card grid | Rebuild wireframe marketing site as the staff app |
| Use PRD Glossary terms in chrome (SENCO, EHCP, Gap, Determination) | Use IEP, SENDCO, “client/patient”, Grade 5 |
| Keep SRE explainability visually calm and structured | Gamify Determinations or show AI “confidence %” |
| Stack cards on Hybrid Client; prioritise capture | Force desktop three-column dashboards on phone |
| Secondary green only for secondary CTAs | Paint Gaps green because “secondary is green” |
| Light soft shadows | Purple glow, dark mode, neon charts |
| SVG / licensed logo when available; script wordmark interim | Comic Sans as production brand font |

## Key mockups

Visual references only — this DESIGN.md and EXPERIENCE.md win on conflict.

- [`mockups/teacher-capture.html`](mockups/teacher-capture.html) — Hybrid Teacher Capture Intervention (UJ-1)
- [`mockups/senco-evidence-base.html`](mockups/senco-evidence-base.html) — SENCO Evidence Base, Gaps, Reasoning Pathway (UJ-2)
- [`mockups/school-report.html`](mockups/school-report.html) — School Leader School Report (UJ-3)

Remaining IA surfaces are spine-only until Architecture / build.
