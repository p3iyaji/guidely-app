/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../navigation.js', () => ({
    navigateToAccessDenied: vi.fn(),
}));

import { apiFetch } from '../api/client.js';
import { navigateToAccessDenied } from '../navigation.js';

describe('apiFetch skipForbiddenRedirect', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        globalThis.fetch = vi.fn();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('does not navigate on 403 when skipForbiddenRedirect is true', async () => {
        globalThis.fetch.mockResolvedValueOnce({
            status: 403,
            clone: () => ({
                json: async () => ({ code: 'forbidden', message: 'Forbidden' }),
            }),
            json: async () => ({ code: 'forbidden' }),
        });

        const response = await apiFetch('/api/v1/drafts/x', {
            method: 'PATCH',
            skipForbiddenRedirect: true,
        });

        expect(response.status).toBe(403);
        expect(navigateToAccessDenied).not.toHaveBeenCalled();
    });

    it('navigates on 403 by default', async () => {
        globalThis.fetch.mockResolvedValueOnce({
            status: 403,
            clone: () => ({
                json: async () => ({ code: 'forbidden', message: 'Forbidden' }),
            }),
            json: async () => ({ code: 'forbidden' }),
        });

        await apiFetch('/api/v1/drafts/x', { method: 'PATCH' });

        expect(navigateToAccessDenied).toHaveBeenCalled();
    });
});
