/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useSession } from '../features/auth/session.js';
import ReviewCyclesPage from '../pages/ReviewCyclesPage.vue';
import EvidenceBasePage from '../pages/EvidenceBasePage.vue';

const openCycles = [
    {
        id: 'rc_1',
        pupil_id: 'pup_1',
        type: 'annual_review',
        type_label: 'Annual Review',
        due_on: '2026-09-20',
        ehcp_linked: false,
        status: 'open',
        pupil: {
            id: 'pup_1',
            given_name: 'Maya',
            family_name: 'Okonkwo',
            year_group: 'Year 4',
        },
    },
];

describe('ReviewCyclesPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async () => jsonResponse({ data: openCycles }));
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
     * @param {{ cycles?: unknown[], status?: number, path?: string, closeStatus?: number, pupilsStatus?: number }} [options]
     */
    async function mountPage(role = 'senco', options = {}) {
        const {
            cycles = openCycles,
            status = 200,
            path = '/review-cycles',
            closeStatus = 200,
            pupilsStatus = 200,
        } = options;

        fetchMock.mockImplementation(async (url, init = {}) => {
            const href = String(url);
            const method = String(init.method ?? 'GET').toUpperCase();

            if (href.includes('/close') && method === 'POST') {
                if (closeStatus >= 400) {
                    return jsonResponse({ message: 'Unable to close Review Cycle.' }, closeStatus);
                }

                return jsonResponse({ data: { ...openCycles[0], status: 'closed' } });
            }

            if (href.includes('/api/v1/review-cycles') && method === 'POST') {
                return jsonResponse({ data: openCycles[0] }, 201);
            }

            if (href.includes('/api/v1/review-cycles')) {
                if (status === 403) {
                    return jsonResponse({ message: 'You don’t have access.', code: 'forbidden' }, 403);
                }

                return jsonResponse({ data: cycles }, status);
            }

            if (href.includes('/api/v1/pupils')) {
                if (pupilsStatus >= 400) {
                    return jsonResponse({ message: 'error' }, pupilsStatus);
                }

                return jsonResponse({
                    data: [
                        {
                            id: 'pup_1',
                            given_name: 'Maya',
                            family_name: 'Okonkwo',
                        },
                    ],
                });
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
                { path: '/review-cycles', name: 'review-cycles', component: ReviewCyclesPage },
                {
                    path: '/pupils/:id',
                    name: 'pupil-detail',
                    component: EvidenceBasePage,
                },
            ],
        });

        await router.push(path);
        await router.isReady();

        const wrapper = mount(ReviewCyclesPage, {
            global: {
                plugins: [router],
            },
        });

        await flushPromises();

        return { wrapper, router };
    }

    it('lists open Review Cycles with pupil, type, due date and Create for SENCO', async () => {
        const { wrapper } = await mountPage('senco');

        expect(wrapper.find('[data-testid="review-cycles-page"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="review-cycles-disclaimer"]').text()).toContain(
            'not diagnoses, funding decisions, or statutory determinations',
        );
        expect(wrapper.find('[data-testid="review-cycle-pupil-name"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="review-cycle-type-label"]').text()).toContain('Annual Review');
        expect(wrapper.find('[data-testid="review-cycle-due-on"]').text()).toContain('20');
        expect(wrapper.find('[data-testid="review-cycles-create-open"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="review-cycle-close"]').exists()).toBe(true);
        expect(wrapper.text().toLowerCase()).not.toContain('confidence');
        expect(wrapper.text().toLowerCase()).not.toContain('auto-approved');
    });

    it('hides Create and Close for School Leader', async () => {
        const { wrapper } = await mountPage('school_leader');

        expect(wrapper.find('[data-testid="review-cycles-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="review-cycles-create-open"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="review-cycles-create-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="review-cycle-close"]').exists()).toBe(false);
    });

    it('requests the 7-day window when that chip is selected', async () => {
        const { wrapper, router } = await mountPage('senco');

        await wrapper.find('[data-testid="review-cycles-window-7"]').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.query.window).toBe('7');
        const urls = fetchMock.mock.calls.map((call) => String(call[0]));
        expect(urls.some((url) => url.includes('window=7'))).toBe(true);
    });

    it('loads TopBar q from the due list query string', async () => {
        await mountPage('senco', { path: '/review-cycles?q=Maya' });

        const urls = fetchMock.mock.calls.map((call) => String(call[0]));
        expect(urls.some((url) => url.includes('q=Maya'))).toBe(true);
    });

    it('shows empty state when there are no due Review Cycles', async () => {
        const { wrapper } = await mountPage('senco', { cycles: [] });

        expect(wrapper.find('[data-testid="review-cycles-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="review-cycles-empty-copy"]').text()).toContain(
            'No Review Cycles due in this window.',
        );
        expect(wrapper.find('[data-testid="review-cycles-list"]').exists()).toBe(false);
    });

    it('shows access error for forbidden Review Cycle responses', async () => {
        const { wrapper } = await mountPage('teacher', { status: 403 });

        expect(wrapper.find('[data-testid="review-cycles-error"]').text()).toContain('You don’t have access.');
    });

    it('posts Create form body then reloads the due list', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="review-cycles-create-open"]').trigger('click');
        await wrapper.find('[data-testid="review-cycle-pupil"]').setValue('pup_1');
        await wrapper.find('[data-testid="review-cycle-due-on"]').setValue('2026-09-20');
        await wrapper.find('[data-testid="review-cycles-create-form"] form').trigger('submit');
        await flushPromises();

        const createCall = fetchMock.mock.calls.find(([url, init]) => {
            return String(url).includes('/api/v1/review-cycles')
                && !String(url).includes('/close')
                && String(init?.method ?? 'GET').toUpperCase() === 'POST';
        });

        expect(createCall).toBeTruthy();
        expect(JSON.parse(createCall[1].body)).toEqual({
            pupil_id: 'pup_1',
            type: 'annual_review',
            due_on: '2026-09-20',
            ehcp_linked: false,
        });

        const urls = fetchMock.mock.calls.map((call) => String(call[0]));
        expect(urls.filter((url) => url.includes('/api/v1/review-cycles?')).length).toBeGreaterThan(1);
    });

    it('posts Close then reloads the due list', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="review-cycle-close"]').trigger('click');
        await flushPromises();

        const closeCall = fetchMock.mock.calls.find(([url, init]) => {
            return String(url).includes('/api/v1/review-cycles/rc_1/close')
                && String(init?.method ?? 'GET').toUpperCase() === 'POST';
        });

        expect(closeCall).toBeTruthy();
        const urls = fetchMock.mock.calls.map((call) => String(call[0]));
        expect(urls.filter((url) => url.includes('/api/v1/review-cycles?')).length).toBeGreaterThan(1);
    });

    it('shows an error when Close fails', async () => {
        const { wrapper } = await mountPage('senco', { closeStatus: 500 });

        await wrapper.find('[data-testid="review-cycle-close"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="review-cycles-close-error"]').text()).toContain(
            'Unable to close Review Cycle.',
        );
        expect(wrapper.find('[data-testid="review-cycles-list"]').exists()).toBe(true);
    });

    it('deep-links due-list rows to the pupil Evidence Base', async () => {
        const { wrapper, router } = await mountPage('senco');

        await wrapper.find('[data-testid="review-cycle-row-link-rc_1"]').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('pupil-detail');
        expect(router.currentRoute.value.params.id).toBe('pup_1');
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
