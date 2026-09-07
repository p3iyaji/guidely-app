/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useSession } from '../features/auth/session.js';
import GapsPage from '../pages/GapsPage.vue';
import EvidenceBasePage from '../pages/EvidenceBasePage.vue';

const openGaps = [
    {
        id: 'gap_1',
        pupil_id: 'pup_1',
        determination_id: 'det_1',
        dimension: 'Sequential Compliance',
        result: 'unmet',
        result_label: 'Not met',
        pupil: {
            id: 'pup_1',
            given_name: 'Maya',
            family_name: 'Okonkwo',
            year_group: 'Year 4',
        },
    },
];

describe('GapsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/gaps')) {
                return jsonResponse({ data: openGaps });
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
     * @param {{ gaps?: unknown[], status?: number }} [options]
     */
    async function mountPage(role = 'senco', options = {}) {
        const { gaps = openGaps, status = 200 } = options;

        fetchMock.mockImplementation(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/gaps')) {
                if (status === 403) {
                    return jsonResponse({ message: 'You don’t have access.', code: 'forbidden' }, 403);
                }

                return jsonResponse({ data: gaps }, status);
            }

            return jsonResponse({});
        });

        useSession().setUser({
            id: 'usr_1',
            name: 'Test User',
            email: 'test@example.com',
            role,
            tenant_id: 'ten_1',
        });

        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/gaps', name: 'gaps', component: GapsPage },
                {
                    path: '/pupils/:id',
                    name: 'pupil-detail',
                    component: EvidenceBasePage,
                },
            ],
        });

        await router.push('/gaps');
        await router.isReady();

        const wrapper = mount(GapsPage, {
            global: {
                plugins: [router],
            },
        });

        await flushPromises();

        return { wrapper, router };
    }

    it('lists open Gaps with pupil, dimension, result and deep-links to Evidence Base', async () => {
        const { wrapper, router } = await mountPage('senco');

        expect(wrapper.find('[data-testid="gaps-page"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="gaps-disclaimer"]').text()).toContain(
            'not diagnoses, funding decisions, or statutory determinations',
        );
        expect(wrapper.find('[data-testid="gap-pupil-name"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="gap-dimension"]').text()).toContain('Sequential Compliance');
        expect(wrapper.find('[data-testid="gap-result"]').text()).toContain('Not met');

        await wrapper.find('[data-testid="gap-row-link-gap_1"]').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('pupil-detail');
        expect(router.currentRoute.value.params.id).toBe('pup_1');
        expect(router.currentRoute.value.query.focus).toBe('determination');
        expect(router.currentRoute.value.query.determination).toBe('det_1');
        expect(wrapper.text().toLowerCase()).not.toContain('confidence');
        expect(wrapper.find('[data-testid="gaps-disclaimer"]').text()).toContain('not diagnoses');
    });

    it('shows empty state when there are no open Gaps', async () => {
        const { wrapper } = await mountPage('senco', { gaps: [] });

        expect(wrapper.find('[data-testid="gaps-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="gaps-empty-copy"]').text()).toContain('No open Gaps');
        expect(wrapper.find('[data-testid="gaps-list"]').exists()).toBe(false);
    });

    it('shows access error for forbidden Gaps responses', async () => {
        const { wrapper } = await mountPage('teacher', { status: 403 });

        expect(wrapper.find('[data-testid="gaps-error"]').text()).toContain('You don’t have access.');
    });
});

/**
 * @param {unknown} body
 * @param {number} [status]
 */
function jsonResponse(body, status = 200) {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
