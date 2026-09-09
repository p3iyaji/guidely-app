/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import FeatureFlagsPage from '../pages/FeatureFlagsPage.vue';

const sampleFlags = {
    trust_dashboard: false,
    connectors: true,
    advanced_documentation_packs: false,
    review_cycle_automation: false,
    portfolio_benchmarking: false,
    compliance_alerts: false,
    safeguarding_ingest: false,
};

describe('FeatureFlagsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/tenant/feature-flags') && method === 'GET') {
                return jsonResponse({ data: sampleFlags });
            }

            if (path.includes('/api/v1/tenant/feature-flags') && method === 'PATCH') {
                const body = JSON.parse(String(options.body ?? '{}'));

                return jsonResponse({
                    data: {
                        ...sampleFlags,
                        [body.key]: body.enabled === true,
                    },
                });
            }

            return jsonResponse({});
        });

        vi.stubGlobal('fetch', fetchMock);

        Object.defineProperty(document, 'cookie', {
            writable: true,
            configurable: true,
            value: 'XSRF-TOKEN=test-token',
        });
    });

    afterEach(() => {
        useSession().setUser(null);
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    /**
     * @param {string} [role]
     */
    async function mountPage(role = 'tenant_admin') {
        useSession().setUser({
            id: 'usr_1',
            name: 'Ada Admin',
            email: 'ada@example.com',
            role,
            tenant_id: 'ten_1',
        });

        const wrapper = mount(FeatureFlagsPage);
        await flushPromises();

        return wrapper;
    }

    it('lists Tenant flags and PATCHes a toggle', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="feature-flags-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="flag-label-connectors"]').text()).toBe('Connectors');
        expect(wrapper.find('[data-testid="flag-toggle-connectors"]').element.checked).toBe(true);

        await wrapper.find('[data-testid="flag-toggle-trust_dashboard"]').setValue(true);
        await flushPromises();

        const patch = fetchMock.mock.calls.find((call) => {
            const path = String(call[0]);
            const method = String(call[1]?.method ?? 'GET').toUpperCase();

            return path.includes('/api/v1/tenant/feature-flags') && method === 'PATCH';
        });

        expect(patch).toBeTruthy();
        expect(JSON.parse(String(patch[1].body))).toEqual({
            key: 'trust_dashboard',
            enabled: true,
        });
    });

    it('does not load flags for a Teacher', async () => {
        const wrapper = await mountPage('teacher');

        expect(wrapper.find('[data-testid="feature-flags-error"]').text()).toContain('You don’t have access.');
        expect(wrapper.find('[data-testid="feature-flags-list"]').exists()).toBe(false);
    });
});

/**
 * @param {unknown} body
 * @param {number} [status]
 */
function jsonResponse(body, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    };
}
