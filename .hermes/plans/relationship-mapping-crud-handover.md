# Handover: RelationshipMapping CRUD (guidely-app)

Session stopped at user request. Implementation is COMPLETE and untested; only tests remain.

## Task
Full CRUD for Relationship Mappings in /mnt/c/Users/admin/Documents/guidely-app, mirroring the completed ontology-term CRUD patterns (NeedTerm/OutcomeTerm/ThresholdTerm).

## Status: implementation done, NO tests written or run yet

### Backend — all present and wired
- `app/Http/Controllers/Api/V1/RelationshipMappingController.php` (modified) — full index/store/show/update/destroy with AuditWriter calls. NOTE: HEAD version had a read-only index gated on EvidenceRecord/Pupil create permission; working tree now has proper CRUD + audit.
- `app/Policies/RelationshipMappingPolicy.php` (new, untracked) — viewAny/create = active tenant admin; view/update/delete also require mapping on effective ontology version (`EffectiveOntologyVersion::id()`).
- Policy registered: `app/Providers/AppServiceProvider.php` line 91 `Gate::policy(RelationshipMapping::class, RelationshipMappingPolicy::class);` (imports at lines 16, 46 — already committed in HEAD? verify with git diff; the Gate registration may be part of working tree).
- Requests (new, untracked):
  - `app/Http/Requests/Api/V1/StoreRelationshipMappingRequest.php`
  - `app/Http/Requests/Api/V1/UpdateRelationshipMappingRequest.php`
  - `app/Http/Requests/Api/V1/Concerns/ValidatesRelationshipMappingFields.php` — rules: code (required, max64, regex ^[A-Z0-9][A-Z0-9_-]*$, unique per ontology_version_id), label, relationship_type (lowercase snake_case regex), from_domain/to_domain in [need, setting, provision, outcome, threshold], from_term_id/to_term_id must exist on effective version via domain model `forVersion($versionId)->whereKey()`, is_active/sort_order optional. After-hook fails validation if no published ontology version.
- Resource: `app/Http/Resources/Api/V1/RelationshipMappingResource.php` (modified) — added `is_active`.
- Model: `app/Domain/Ontology/RelationshipMapping.php` (modified) — added `resolveRouteBinding()` scoped to effective ontology version (`forVersion($versionId)`).
- Audit enum cases already in HEAD: `AuditEventType::RelationshipMappingCreated/Updated/Deleted` = 'ontology.relationship_mapping.created|updated|deleted' (lines 65–67).
- Route (committed in HEAD, line 171): `Route::apiResource('ontology/relationship-mappings', RelationshipMappingController::class)->parameters(['relationship-mappings' => 'relationshipMapping'])->names('api.v1.ontology.relationship-mappings');`

### Frontend — all present
- `resources/js/pages/RelationshipMappingsPage.vue` (new, untracked) — 727 lines, mirrors OutcomeTermsPage/ThresholdTermsPage pattern.
- Router: `resources/js/router/index.js` line 18 import + lines 238–241 route entry (path 'relationship-mappings').
- Nav: `resources/js/features/shell/navByRole.js` line 115 `{ key: 'relationship-mappings', label: 'Relationship mappings', to: '/relationship-mappings' }`.

### Factory — fixed for tests
- `database/factories/RelationshipMappingFactory.php` (modified) — now pins `ontology_version_id` to `PilotOntology::ensurePublishedVersion()->id` (was random published version). This mirrors the ThresholdTerm factory fix that resolved update/delete 404s. Has `forVersion()` and `inactive()` states.

## Remaining work (in order)
1. Write `tests/Feature/RelationshipMappingTest.php` — mirror `tests/Feature/ThresholdTermTest.php` (254 lines, all passing). Key setup: Tenant::factory()->create(), User::factory()->forTenant($tenant)->tenantAdmin()->create(), actingAs admin; terms via NeedTerm::factory()/ProvisionTerm::factory() etc. (factories pin to PilotOntology effective version); mappings via RelationshipMapping::factory(). Cover index, store (success + validation failures: bad code regex, duplicate code, invalid domain, nonexistent term id), show, update, destroy, 403 for non-admins.
2. Write `tests/Feature/RelationshipMappingPolicyTest.php` — mirror `tests/Feature/ThresholdTermPolicyTest.php` (88 lines). DataProvider of denied roles: Senco, Teacher, SupportStaff, SchoolLeader, TrustSendLead, TrustExecutive, PlatformOperator; trust-role flag setup for TrustDashboard.
3. Run: `php artisan test --filter="RelationshipMapping"` — fix failures.
4. Write `resources/js/tests/RelationshipMappingsPage.test.js` — mirror `resources/js/tests/ThresholdTermsPage.test.js` (357 lines).
5. Run: `npx vitest run resources/js/tests/RelationshipMappingsPage.test.js`.
6. Regression: `php artisan test --filter="NeedTerm|ThresholdTerm"` and `npm run build`.

## Environment notes (WSL)
- Frontend tooling needs linux binaries already installed this session: `npm install --no-save @rollup/rollup-linux-x64-gnu@4.63.1 @esbuild/linux-x64@0.28.2` (re-run if node_modules was reinstalled).
- Backend tests run fine with `php artisan test`.

## Pitfalls learned from ThresholdTerm work (apply here)
- Term/mapping factories MUST pin to `PilotOntology::ensurePublishedVersion()` or update/delete return 404 (route binding resolves against effective version).
- Model closures: use proper `function (Builder $query): void { ... }` syntax, not arrow functions with void return type.
- Validation concern pattern: presence = 'required' for store, 'sometimes' for update; unique rule scoped to ontology_version_id with ignore on update.

## Verification state at stop time
- No tests have been run against the RelationshipMapping implementation yet (backend or frontend).
- Git status: 4 modified tracked files + 5 untracked new files listed above; branch main, last commit 0af10c1 "outcome and threshold crud updated". Do NOT commit unless asked.
