# Guidely App — Fix Handover Note (fresh session)

> **For Hermes:** This is a handover, not an execution plan. Read it top to bottom, then work the tasks in order. Every fact below was verified by running commands on 2026-09-09; re-run the verification commands before claiming anything fixed.

**Goal:** Fix the two failing test suites (1 backend test, 17 frontend tests) and close the half-wired safeguarding-signal ingest gap (story 7.5).

**Stack:** Laravel 13 (PHP 8.4 via Herd) JSON API + Vue 3.5 SPA (Vite 8 / Tailwind 4), no Inertia, SQLite local. Root: `C:\Users\admin\Documents\guidely-app` (git-bash path `/c/Users/admin/Documents/guidely-app`).

---

## Environment facts (verified)

- PHP binary for artisan tests: `"/c/Users/admin/.config/herd/bin/php.bat" artisan test ...`
  - Plain `php` on PATH is NOT the right one; use the Herd path above.
- Node v26.5.0, npm present. `vendor/` and `node_modules/` are installed.
- `.env` was created from `.env.example` + `key:generate` during analysis (untracked — expected).
- Git branch `main`, clean except `composer.lock` modified (carbon-doctrine-types 3.2.0→3.2.1 bump from a composer run; decide whether to keep or revert with the session owner).
- Windows quirks that will bite you:
  - Terminal runs git-bash, not PowerShell. Use POSIX syntax.
  - `taskkill /F /PID <pid>` (single slash) works in git-bash; `//F` fails. Find PIDs via `netstat -ano | grep <port>`.
  - The patch tool's node linter can report a bogus `Cannot find module 'C:\c\Users\admin\...'` error after editing `.js` files — Windows path-mangling bug, not real; verify with `npm run build` instead.

## Baseline (measured 2026-09-09)

| Check | Command | Result |
|---|---|---|
| Backend tests | `"/c/Users/admin/.config/herd/bin/php.bat" artisan test` | **737 passed, 1 failed** (4229 assertions) |
| Frontend tests (default) | `npm test` | **17 failed / 269 passed** (3 files: offlineDraftQueue.test.js, DraftsPage.test.js, CapturePage.test.js) |
| Frontend tests (flagged) | `NODE_OPTIONS="--no-experimental-webstorage" npm test` | **all 32 files / 286 tests pass** |
| Build | `npm run build` | passes clean (~450ms) |

---

## Task 1 — Backend: one failing ontology permission test (root cause found, decision needed)

**Failing:** `tests/Feature/OntologyVersionMappingTest.php::test_tenant_admin_can_list_need_terms_but_not_capture_ontology_domains`
- Expects tenant admin to get **403** on all five capture-domain ontology endpoints.
- Four return 403; `/api/v1/ontology/provision-terms` returns **200**.

**Root cause (verified):** commit `df2c7b4` ("crud gaps updated") deliberately:
1. added `'manage_provision_terms'` to the TenantAdmin permission set — `app/Domain/Identity/AccessCatalogue.php:211`, and
2. created `app/Policies/ProvisionTermPolicy.php` whose `viewAny()` returns `$this->isActiveTenantAdmin($user)` (line 13).

So tenant admins can now list provision terms **by design**; the test at lines ~330–341 still asserts 403 for it. The loop is:

```php
foreach ([
    '/api/v1/ontology/outcome-terms',
    '/api/v1/ontology/threshold-terms',
    '/api/v1/ontology/relationship-mappings',
    '/api/v1/ontology/setting-terms',
    '/api/v1/ontology/provision-terms',   // <- line 337, the offender
] as $path) {
    $this->actingAs($admin)->getJson($path)->assertForbidden()
```

**Decision (recommend option A):**
- **A. Update the test** — remove `'/api/v1/ontology/provision-terms'` from the 403 loop and add a separate assertion that tenant admin gets 200 on it (mirroring the existing need-terms positive assertion just above). This matches the deliberate policy + catalogue change in df2c7b4.
- **B. Tighten the policy** — make `ProvisionTermPolicy::viewAny` require a capture-domain permission instead of plain tenant-admin. Only pick this if the session owner says provision terms should stay admin-only; it's a bigger behavior change (breaks any FE that relies on tenant admins seeing them).

**Verify:**
```bash
"/c/Users/admin/.config/herd/bin/php.bat" artisan test --filter=OntologyVersionMappingTest   # expect all pass
"/c/Users/admin/.config/herd/bin/php.bat" artisan test                                       # expect 738 passed, 0 failed
```

---

## Task 2 — Frontend: 17 test failures caused by Node 26 experimental Web Storage (root cause found)

**Symptom:** `npm test` fails with 17 errors in exactly three files: `offlineDraftQueue.test.js`, `DraftsPage.test.js`, `CapturePage.test.js`. All share one root cause.

**Root cause (verified):** Node v26 enables experimental Web Storage (`globalThis.localStorage`) which collides with jsdom's implementation and silently breaks storage-dependent tests. Proof: `NODE_OPTIONS="--no-experimental-webstorage" npm test` → **all 286 pass**.

**Fix options, in order of preference:**
1. **Pin the flag into the test script** (cross-platform via npm): edit `package.json`:
   ```json
   "test": "vitest run --no-experimental-webstorage"
   ```
   ⚠️ NOT verified: whether vitest 3.2.7 forwards unknown CLI flags to Node. If it errors, fall back to option 2 or 3. (A zero-dep `node --no-experimental-webstorage node_modules/vitest/vitest.mjs run ...` was tried and is flaky on Windows — filter matching misbehaves; don't rely on it.)
2. **Set the flag via cross-env** if vitest rejects the CLI flag: add `cross-env` to devDependencies, `"test": "cross-env NODE_OPTIONS=--no-experimental-webstorage vitest run"`.
3. **Pin Node 22/24**: README already says "Node 22+". Add an `.nvmrc` (`22`) and an `engines` field; ask the session owner to switch their local Node. This is the cleanest long-term but requires a host change.

**Verify:**
```bash
npm test        # expect: Test Files 32 passed (32), Tests 286 passed (286)
npm run build   # still passes
```

---

## Task 3 — Gap: safeguarding-signal ingest has a full backend but zero frontend callers (story 7.5 half-wired)

**Backend (complete, tested):**
- Route: `PUT /api/v1/pupils/{pupil}/safeguarding-signal` → `SafeguardingSignalController::upsert` (`routes/api.php:208`, behind `feature:safeguarding_ingest`).
- Request contract — `app/Http/Requests/Api/V1/UpsertSafeguardingSignalRequest.php`:
  - Body: `{ "present": boolean (required), "severity": "low"|"medium"|"high" }`
  - `severity` is `prohibited_if:present,false`, `required_if:present,true`.
  - **Any other key in the body → validation error** ("Only present and severity may be sent."). Do NOT send notes/evidence/etc.
- Policy — `app/Policies/SafeguardingSignalPolicy.php::upsert`: only **SENCO or SchoolLeader**, active tenant staff, pupil's school must be active, user must `canAccessSchool($school)`.
- Response: 200 with `SafeguardingSignalResource` shape: `{ id, pupil_id, given_name, family_name, school_id, present, severity (nullable string), updated_at }`.
- Severity enum values (`app/Domain/Pupils/SafeguardingSeverity.php`): `low`, `medium`, `high`.

**Frontend (missing):**
- `resources/js/pages/SafeguardingContextPage.vue` is **read-only**: GETs `/api/v1/safeguarding-signals`, renders a list with presence + severity category. No form, no PUT anywhere in the codebase (verified by grep).
- The page already handles the feature flag: on 403 with `payload.code === 'feature_not_available'` it shows `<FeatureFlaggedEmpty>` — reuse that pattern for the upsert response.
- API helper: `apiFetch(url, options)` from `resources/js/api/client.js`.

**Where to build the form (decision point — ask session owner if unsure):**
- **Option A (recommended): add an inline "record context" section on SafeguardingContextPage.vue.** The page is already role-gated and flag-aware; each list row could get a small per-pupil upsert control, or a single form with pupil picker. Keep it minimal: presence toggle + severity select (disabled when present=false) + save button.
- **Option B: add to the pupil detail view** — `pupils/:id` route renders `EvidenceBasePage.vue`. More discoverable per-pupil but that page is about evidence, not safeguarding context.

**Implementation notes for Option A:**
1. Follow existing page conventions (see SafeguardingContextPage.vue script: `loadSeq` guard pattern, `apiFetch`, Card/LoadingSkeleton components from `resources/js/shared/ui/`).
2. PUT body must be exactly `{ present, severity }`. When unflagging (`present=false`), send `severity: null` or omit it — the request forbids extra keys but allows nullable severity; safest is to send only `present:false` (severity rule is `prohibited_if:present,false`, so sending a value would 422).
3. On success, refresh the list (`loadSignals()` again) and show the returned resource's updated_at.
4. Handle 403 (no permission — SENCO/SchoolLeader only), 404 (pupil gone), 422 (validation — surface field errors), and `feature_not_available` code.
5. Add a Vitest for the new component following existing page-test patterns (look at how other pages are tested under `resources/js/**`).

**Verify:**
```bash
npm test          # all pass including new tests
npm run build     # clean
# Manual: log in as SENCO, open /safeguarding-context, flag a pupil low/medium/high, unflag it; check audit trail picked up SafeguardingSignalUpserted events.
```

---

## Remaining gaps (NOT part of this fix — record for later)

1. **Compliance alert thresholds**: `GET /api/v1/compliance-alert-thresholds` exists but no FE consumer and no PATCH route; AlertsPage copy says "meets a configured threshold" with no UI surface.
2. **Orphaned ontology endpoints** (FE never calls): `/ontology/outcome-terms`, `/threshold-terms`, `/relationship-mappings`, `/rules`. Decide: wire in or document as API-only.
3. **Dead pages**: `resources/js/pages/HomePage.vue` and `ComingSoonPage.vue` are not referenced by `router/index.js` (home route uses HomeDashboard). Remove or route intentionally.
4. **Thin e2e coverage**: only 2 Playwright specs (`login-shell`, `import-pupils`) for ~24 routed pages.

## Suggested commit order

1. Task 1 fix → `fix(ontology): align tenant-admin provision-terms expectation with df2c7b4 policy` (or the policy-tightening variant)
2. Task 2 fix → `test(fe): disable Node experimental web storage for vitest on Node 26`
3. Task 3 feature → `feat(7.5): ingest minimised safeguarding context from SafeguardingContextPage`

## Open questions for the session owner

- Task 1: option A (update test) or B (tighten policy)?
- Task 2: flag in package.json, cross-env, or pin Node via .nvmrc?
- Task 3: form on SafeguardingContextPage (A) or pupil detail/EvidenceBasePage (B)?
- Keep or revert the `composer.lock` carbon-doctrine-types bump currently sitting uncommitted?
