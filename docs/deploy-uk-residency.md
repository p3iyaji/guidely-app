# UK residency deploy notes

Operator-facing targets for primary database, object storage, and backups (NFR-9, AD-14).

## Policy

GuidelyEdu Pilot and production workloads that hold Tenant pupil or staff data must keep **primary database**, **object storage**, and **backups** in the **United Kingdom** or a **UK-adequate** jurisdiction. Do not place these stores in a non-UK / non-adequate region by default.

## Targets

| Component | Target | Notes |
|-----------|--------|-------|
| Primary database | UK or UK-adequate region | PostgreSQL for non-local environments. Prefer a UK cloud region (for example `eu-west-2` / London) or an equivalent UK-adequate host. |
| Object storage | UK or UK-adequate region | S3-compatible encrypted buckets in the same residency class as the primary DB. Encryption at rest and in transit required. |
| Backups | UK or UK-adequate region | Database snapshots and object-storage replicas must remain in UK / UK-adequate regions. Cross-region copies outside that set are not allowed without an explicit adequacy decision. |

## Local development

Local SQLite and developer machines are exempt from cloud residency pinning, but they must not be used as a durable store for live Pilot pupil data.

## Checklist before go-live

- [ ] Primary DB host region is UK or UK-adequate
- [ ] Object storage bucket region matches the same residency class
- [ ] Backup / snapshot destinations are UK or UK-adequate
- [ ] Encryption in transit and at rest is enabled for DB and object storage
- [ ] Operators documented the chosen regions in the environment runbook

Related: [School onboarding runbook](runbooks/school-onboarding.md).
