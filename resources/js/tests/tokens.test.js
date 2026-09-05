import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

describe('DESIGN.md tokens in CSS @theme', () => {
    const css = readFileSync(resolve(root, 'resources/css/app.css'), 'utf8');

    it('defines canvas, primary, secondary, page spacing, radius, and shell sizes', () => {
        expect(css).toMatch(/@theme\s*\{/);
        expect(css).toMatch(/--color-canvas:\s*#F0F0F5/i);
        expect(css).toMatch(/--color-primary:\s*#5B63E6/i);
        expect(css).toMatch(/--color-secondary:\s*#10B981/i);
        expect(css).toMatch(/--spacing-page:\s*24px/);
        expect(css).toMatch(/--radius-lg:\s*12px/);
        expect(css).toMatch(/--spacing-topbar-height:\s*56px/);
        expect(css).toMatch(/--spacing-sidebar-width:\s*240px/);
    });

    it('is light-mode only (no dark theme block)', () => {
        expect(css).not.toMatch(/@media\s*\(\s*prefers-color-scheme:\s*dark\s*\)/);
        expect(css).not.toMatch(/\.dark\s*\{/);
    });
});
