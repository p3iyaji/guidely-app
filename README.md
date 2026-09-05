# GuidelyEdu

UK SEND documentation platform — classic **root Laravel 13** JSON API with a **Vue 3.5 SPA** under `resources/js` (Vite 8, Tailwind 4). Product UI talks to `/api/v1/*`. **Inertia is not used.**

## Requirements

- PHP 8.3+ (8.4 recommended; Laravel Herd works well)
- Composer
- Node 22+ and npm
- SQLite for local development (default)
- PostgreSQL 16+ for non-local environments

## Local setup

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
```

## Run locally

**API / SPA shell (Laravel):**

```bash
php artisan serve
```

App: [http://127.0.0.1:8000](http://127.0.0.1:8000)  
Health: [http://127.0.0.1:8000/api/v1/health](http://127.0.0.1:8000/api/v1/health)

**Frontend (Vite HMR):**

```bash
npm run dev
```

Keep `php artisan serve` running in another terminal while developing the SPA.

**Queue worker:**

```bash
php artisan queue:work
```

Local default queue driver is `database`. Non-local environments should use Redis.

## Verification

```bash
php artisan test --filter=Health
npm test
php artisan route:list --path=api/v1/health
npm run build
```

## Layout

| Path | Role |
|------|------|
| `app/`, `routes/`, `composer.json` | Laravel application root |
| `routes/api.php` | Versioned JSON API (`/api/v1/…`) |
| `resources/js` | Vue 3 SPA (Vue Router) |
| `resources/views/app.blade.php` | SPA HTML shell only (loads Vite) |
| `resources/css/app.css` | DESIGN.md tokens via Tailwind `@theme` |
| `_bmad/`, `_bmad-output/`, `docs/` | Planning / BMAD artefacts |
| `_archive/` | Historical superseded scaffolds — do not treat as live app |

## Database

- **Local:** SQLite (`DB_CONNECTION=sqlite`)
- **Non-local:** PostgreSQL (`DB_CONNECTION=pgsql`) — set host, database, username, and password in `.env`

## SSO readiness

Users may store a nullable Tenant-scoped `external_id` for future IdP subject mapping. Tenants store SSO stub fields (`sso_enabled` defaults to false, plus provider / entity_id / client_id placeholders) with no live IdP, ACS, or callback routes in Pilot.

**Open question:** SAML vs OIDC protocol priority for first Trust SSO enablement remains undecided (see Architecture open question #2 in `_bmad-output/planning-artifacts/architecture/architecture-guidely-app-2026-08-20/ARCHITECTURE-SPINE.md`). Do not install Socialite/SAML packages or choose a protocol without an explicit product decision.
