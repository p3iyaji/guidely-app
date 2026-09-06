#!/usr/bin/env node

import { execSync } from 'node:child_process';
import { existsSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const dbPath = resolve(root, 'database/e2e.sqlite');

if (!existsSync(dbPath)) {
    writeFileSync(dbPath, '');
}

// Absolute path so artisan serve --env=e2e never falls back to database/database.sqlite.
writeFileSync(
    resolve(root, '.env.e2e'),
    `APP_NAME=GuidelyEdu
APP_ENV=e2e
APP_KEY=base64:BI1+0TF9vBpcjpETlFMIknOzhxm7wIzRPqZ4cA4kBNQ=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8010

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_GB

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=4

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=sqlite
DB_DATABASE=${dbPath}

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=null
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=array

MAIL_MAILER=array

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8010,127.0.0.1,127.0.0.1:8010
`,
);

const env = {
    ...process.env,
    APP_ENV: 'e2e',
    APP_KEY: 'base64:BI1+0TF9vBpcjpETlFMIknOzhxm7wIzRPqZ4cA4kBNQ=',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: dbPath,
};

execSync('php artisan config:clear --no-interaction', {
    cwd: root,
    env,
    stdio: 'inherit',
});

execSync('php artisan migrate:fresh --seeder=E2eSeeder --force --no-interaction --env=e2e', {
    cwd: root,
    env,
    stdio: 'inherit',
});
