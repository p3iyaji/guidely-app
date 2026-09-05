import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

describe('no Inertia', () => {
    it('does not declare inertia packages in composer.json or package.json', () => {
        const composer = readFileSync(resolve(root, 'composer.json'), 'utf8');
        const pkg = readFileSync(resolve(root, 'package.json'), 'utf8');

        expect(composer.toLowerCase()).not.toMatch(/inertiajs/);
        expect(pkg.toLowerCase()).not.toMatch(/@inertiajs/);
        expect(pkg.toLowerCase()).not.toMatch(/inertiajs/);
    });
});

describe('Vue router IA', () => {
    const forbidden = [
        'parent',
        'portal',
        'lms',
        'clinician',
        'emr',
        'educonnect',
        'lesson',
        'attendance',
        'marketing',
    ];

    it('does not register wireframe parent/LMS/clinician/EduConnect routes', () => {
        const routerSource = readFileSync(
            resolve(root, 'resources/js/router/index.js'),
            'utf8',
        );

        const pathMatches = [...routerSource.matchAll(/path:\s*['"`]([^'"`]+)['"`]/g)].map(
            (m) => m[1],
        );
        const nameMatches = [...routerSource.matchAll(/name:\s*['"`]([^'"`]+)['"`]/g)].map(
            (m) => m[1],
        );

        expect(pathMatches.length).toBeGreaterThan(0);

        for (const value of [...pathMatches, ...nameMatches]) {
            const lower = value.toLowerCase();
            for (const term of forbidden) {
                expect(lower, `route "${value}" must not include "${term}"`).not.toContain(term);
            }
        }
    });
});
