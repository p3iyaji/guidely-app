/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useSession } from '../features/auth/session.js';
import SchoolReportPage from '../pages/SchoolReportPage.vue';
import EvidenceBasePage from '../pages/EvidenceBasePage.vue';

const reportPayload = {
    pupils_in_scope: 5,
    ready: 1,
    gaps: 1,
    review_cycles_due: 1,
    window_days: 30,
    by_status: {
        ready: 1,
        gaps: 1,
        uncovered: 1,
        'not-started': 1,
        evaluating: 1,
    },
    pupils: [
        {
            id: 'pup_ready',
            given_name: 'Maya',
            family_name: 'Okonkwo',
            documentation_status: 'ready',
        },
        {
            id: 'pup_gaps',
            given_name: 'Jordan',
            family_name: 'Lee',
            documentation_status: 'gaps',
        },
        {
            id: 'pup_uncovered',
            given_name: 'Sam',
            family_name: 'Patel',
            documentation_status: 'uncovered',
        },
        {
            id: 'pup_not_started',
            given_name: 'Alex',
            family_name: 'Wright',
            documentation_status: 'not-started',
        },
        {
            id: 'pup_evaluating',
            given_name: 'Riley',
            family_name: 'Chen',
            documentation_status: 'evaluating',
        },
    ],
    cycles_due: [
        {
            id: 'rc_1',
            given_name: 'Maya',
            family_name: 'Okonkwo',
            due_on: '2026-09-20',
            type_label: 'Annual Review',
        },
    ],
};

describe('SchoolReportPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async () => jsonResponse({ data: reportPayload }));
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
     * @param {{ report?: unknown, status?: number, path?: string }} [options]
     */
    async function mountPage(role = 'senco', options = {}) {
        const { report = reportPayload, status = 200, path = '/school-report' } = options;

        fetchMock.mockImplementation(async (url) => {
            const href = String(url);

            if (href.includes('/api/v1/school-report')) {
                if (status === 403) {
                    return jsonResponse({ message: 'You don’t have access.', code: 'forbidden' }, 403);
                }

                if (status >= 400) {
                    return jsonResponse({ message: 'Unable to load School Report.' }, status);
                }

                return jsonResponse({ data: report }, status);
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
                { path: '/school-report', name: 'school-report', component: SchoolReportPage },
                {
                    path: '/pupils/:id',
                    name: 'pupil-detail',
                    component: EvidenceBasePage,
                },
            ],
        });

        await router.push(path);
        await router.isReady();

        const wrapper = mount(SchoolReportPage, {
            global: {
                plugins: [router],
            },
        });

        await flushPromises();

        return { wrapper, router };
    }

    it('renders four SEND documentation KPIs, status pills, and evaluating as in-flight', async () => {
        const { wrapper } = await mountPage('senco');

        expect(wrapper.find('[data-testid="school-report-page"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="school-report-disclaimer"]').text()).toContain(
            'not diagnoses, funding decisions, or statutory determinations',
        );
        expect(wrapper.text()).toContain('SEND documentation');
        expect(wrapper.text()).toContain('not attendance, budget, or curriculum');

        const kpis = wrapper.findAll('[data-testid="kpi-card"]');
        expect(kpis).toHaveLength(4);
        expect(kpis[0].text()).toContain('Pupils in scope');
        expect(kpis[0].find('[data-testid="kpi-value"]').text()).toBe('5');
        expect(kpis[1].text()).toContain('Ready');
        expect(kpis[1].find('[data-testid="kpi-value"]').text()).toBe('1');
        expect(kpis[2].text()).toContain('Gaps');
        expect(kpis[2].find('[data-testid="kpi-value"]').text()).toBe('1');
        expect(kpis[3].text()).toContain('Review Cycles due');
        expect(kpis[3].find('[data-testid="kpi-value"]').text()).toBe('1');

        const rows = wrapper.findAll('[data-testid="school-report-status-row"]');
        expect(rows).toHaveLength(5);
        expect(wrapper.find('[data-testid="school-report-status-evaluating"]').text()).toContain('In flight');
        expect(wrapper.findAll('[data-testid="status-pill"]')).not.toHaveLength(0);
        expect(wrapper.find('[data-testid="school-report-empty"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/edit evidence/i);
        expect(wrapper.find('a[href*="/pupils/"]').exists()).toBe(false);
    });

    it('shows No Pilot data yet and zero counts for an empty School', async () => {
        const { wrapper } = await mountPage('school_leader', {
            report: {
                pupils_in_scope: 0,
                ready: 0,
                gaps: 0,
                review_cycles_due: 0,
                window_days: 30,
                by_status: {
                    ready: 0,
                    gaps: 0,
                    uncovered: 0,
                    'not-started': 0,
                    evaluating: 0,
                },
                pupils: [],
                cycles_due: [],
            },
        });

        expect(wrapper.find('[data-testid="school-report-empty"]').text()).toContain('No Pilot data yet.');
        const values = wrapper.findAll('[data-testid="kpi-value"]').map((node) => node.text());
        expect(values).toEqual(['0', '0', '0', '0']);
    });

    it('filters the on-page drill when a School Leader clicks a KPI or status row', async () => {
        const { wrapper, router } = await mountPage('school_leader');

        await wrapper.findAll('[data-testid="kpi-card-select"]')[0].trigger('click');
        await flushPromises();

        const inScopeNames = wrapper.findAll('[data-testid="school-report-drill-name"]').map((node) => node.text());
        expect(inScopeNames).toEqual([
            'Maya Okonkwo',
            'Jordan Lee',
            'Sam Patel',
            'Alex Wright',
            'Riley Chen',
        ]);

        await wrapper.findAll('[data-testid="kpi-card-select"]')[1].trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="school-report-drill"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="school-report-drill"]').text()).toContain('Ready');
        expect(wrapper.findAll('[data-testid="school-report-drill-row"]')).toHaveLength(1);
        expect(wrapper.find('[data-testid="school-report-drill-name"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="school-report-drill"] [data-testid="status-pill"]').text()).toContain('Ready');

        await wrapper.find('[data-testid="school-report-status-gaps"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="school-report-drill-name"]').text()).toContain('Jordan Lee');

        await wrapper.findAll('[data-testid="kpi-card-select"]')[3].trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="school-report-drill"]').text()).toContain('Review Cycles due');
        expect(wrapper.find('[data-testid="school-report-drill-name"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="school-report-drill-type"]').text()).toContain('Annual Review');
        expect(wrapper.find('[data-testid="school-report-drill-due-on"]').text()).toContain('20');
        expect(wrapper.text()).not.toMatch(/edit evidence/i);
        expect(router.currentRoute.value.name).toBe('school-report');
        expect(wrapper.find('a[href*="evidence"]').exists()).toBe(false);

        await wrapper.find('[data-testid="school-report-drill-clear"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="school-report-drill"]').exists()).toBe(false);
    });

    it('requests window=7 on the first fetch when mounted with that query', async () => {
        await mountPage('senco', { path: '/school-report?window=7' });

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/v1/school-report?window=7'),
            expect.anything(),
        );
        expect(fetchMock.mock.calls[0][0]).toContain('window=7');
    });

    it('passes school_id from the query string on School Report fetch', async () => {
        const { wrapper } = await mountPage('senco', { path: '/school-report?school_id=sch_oak' });

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/v1/school-report?'),
            expect.anything(),
        );
        expect(fetchMock.mock.calls[0][0]).toContain('school_id=sch_oak');
        expect(fetchMock.mock.calls[0][0]).toContain('window=30');

        await wrapper.find('[data-testid="school-report-window-7"]').trigger('click');
        await flushPromises();

        const laterUrls = fetchMock.mock.calls.map((call) => String(call[0]));
        const windowSeven = laterUrls.filter((url) => url.includes('window=7'));

        expect(windowSeven.length).toBeGreaterThan(0);
        expect(windowSeven.at(-1)).toContain('school_id=sch_oak');
    });

    it('requests the selected due window', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="school-report-window-7"]').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/v1/school-report?window=7'),
            expect.anything(),
        );
    });

    it('shows access error for forbidden School Report responses', async () => {
        const { wrapper } = await mountPage('teacher', { status: 403 });

        expect(wrapper.find('[data-testid="school-report-error"]').text()).toContain('You don’t have access.');
    });

    it('shows a load error when the School Report request fails', async () => {
        const { wrapper } = await mountPage('senco', { status: 500 });

        expect(wrapper.find('[data-testid="school-report-error"]').text()).toContain('Unable to load School Report.');
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
