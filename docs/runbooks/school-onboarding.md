# School onboarding runbook

Repeatable operator path to add a School under an existing Tenant, set feature flags, and provision a Tenant Admin — without editing application code (FR-57).

## Prerequisites

- An existing Tenant (created via Platform Operator Pilot bootstrap `POST /api/v1/pilot/tenants`, or already present in the database).
- Operator shell access with `php artisan` against the target environment.
- A unique admin email that is not already registered.

## Command

```bash
php artisan guidely:onboard-school {tenant_id} \
  --school-name="Example Primary" \
  --admin-name="Alex Admin" \
  --admin-email="alex.admin@example.sch.uk" \
  --admin-password="ChooseAStrongPassword1!" \
  --enable-flag=connectors \
  --disable-flag=trust_dashboard
```

| Argument / option | Required | Notes |
|-------------------|----------|-------|
| `tenant_id` | Yes | Existing Tenant ULID |
| `--school-name` | Yes | School display name |
| `--admin-name` | Yes | Tenant Admin display name |
| `--admin-email` | Yes | Unique email for login |
| `--admin-password` | Yes | Must satisfy application password rules |
| `--enable-flag` | No | Repeatable; keys: `trust_dashboard`, `connectors`, `advanced_documentation_packs`, `review_cycle_automation` |
| `--disable-flag` | No | Repeatable; same keys |

Exit code is non-zero when the Tenant is missing or validation fails.

## What the command does

1. Resolves the Tenant by id (fails if missing).
2. Creates an active School with explicit `tenant_id` (no HTTP session / `CurrentTenant` required).
3. Optionally enables/disables Tenant feature flags via `FeatureFlagResolver`.
4. Provisions a Tenant Admin User scoped to that Tenant and attaches the new School.

## Connectors / MIS

Live MIS Connector configuration is **out of scope** for this runbook (Epic 6 / OQ-4). Until Connectors ship, use the Pilot toolkit **Import Template** placeholder download (`GET /api/v1/pilot/import-template`) as the fallback data path. Do not invent Connector credentials or sync steps here.

## After onboarding

1. Confirm the Tenant Admin can sign in at `/login`.
2. Open **Pilot toolkit** to review the disclaimer pack and export the success-metrics stub.
3. Toggle remaining flags from Tenant Admin **Feature flags** when ready.
4. See [UK residency deploy notes](../deploy-uk-residency.md) for primary DB, object storage, and backup targets.
