/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import TrustDashboardPage from '../pages/TrustDashboardPage.vue';
import SchoolReportPage from '../pages/SchoolReportPage.vue';

const leadPayload = {
    pupils_in_scope: 4,
    gaps: 1,
    gap_density: 0.25,
    lateness_rate: 0.5,
    overdue_open_cycles: 1,
    open_cycles: 2,
    by_status: {
        ready: 1,
        gaps: 1,
        uncovered: 1,
        'not-started': 1,
        evaluating: 0,
    },
    escalated_pupils: 0,
    flagged_schools: 0,
    escalations: [],
    schools: [
        {
            school_id: 'sch_oak',
            name: 'Oak Academy',
            pupils_in_scope: 2,
            gaps: 1,
            gap_density: 0.5,
            lateness_rate: 0.5,
            overdue_open_cycles: 1,
            open_cycles: 2,
            by_status: {
                ready: 1,
                gaps: 1,
                uncovered: 0,
                'not-started': 0,
                evaluating: 0,
            },
            escalated_pupils: 0,
            flagged_schools: 0,
            escalations: [],
        },
        {
            school_id: 'sch_ridge',
            name: 'Ridge Academy',
            pupils_in_scope: 2,
            gaps: 0,
            gap_density: 0,
            lateness_rate: 0,
            overdue_open_cycles: 0,
            open_cycles: 0,
            by_status: {
                ready: 0,
                gaps: 0,
                uncovered: 1,
                'not-started': 1,
                evaluating: 0,
            },
            escalated_pupils: 0,
            flagged_schools: 0,
            escalations: [],
        },
    ],
};

const executivePayload = {
    pupils_in_scope: 4,
    gaps: 1,
    gap_density: 0.25,
    lateness_rate: 0.5,
    overdue_open_cycles: 1,
    open_cycles: 2,
    by_status: {
        ready: 1,
        gaps: 1,
        uncovered: 1,
        'not-started': 1,
        evaluating: 0,
    },
    escalated_pupils: 1,
    flagged_schools: 1,
    escalations: [
        {
            rule_id: 'rule_esc',
            rule_code: 'ESC_SEQ_REVIEW',
            rule_label: 'Sequential Compliance — escalation review path',
            pupil_count: 1,
        },
    ],
};

describe('TrustDashboardPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async () => jsonResponse({ data: leadPayload }));
        vi.stubGlobal('fetch', fetchMock);

        Object.defineProperty(document, 'cookie', {
            writable: true,
            configurable: true,
            value: 'XSRF-TOKEN=test-token',
        });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    /**
     * @param {{ payload?: unknown, status?: number, code?: string }} [options]
     */
    async function mountPage(options = {}) {
        const { payload = leadPayload, status = 200, code } = options;

        fetchMock.mockImplementation(async (url) => {
            const href = String(url);

            if (href.includes('/api/v1/trust-dashboard')) {
                if (status === 403 && code === 'feature_not_available') {
                    return jsonResponse({
                        message: 'This feature is not available for this Tenant.',
                        code: 'feature_not_available',
                        feature: 'trust_dashboard',
                    }, 403);
                }

                if (status === 403) {
                    return jsonResponse({ message: 'You don’t have access.', code: 'forbidden' }, 403);
                }

                if (status >= 400) {
                    return jsonResponse({ message: 'Unable to load Trust Dashboard.' }, status);
                }

                return jsonResponse({ data: payload }, status);
            }

            return jsonResponse({});
        });

        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/trust-dashboard', name: 'trust-dashboard', component: TrustDashboardPage },
                { path: '/school-report', name: 'school-report', component: SchoolReportPage },
            ],
        });

        await router.push('/trust-dashboard');
        await router.isReady();

        const wrapper = mount(TrustDashboardPage, {
            global: {
                plugins: [router],
            },
        });

        await flushPromises();

        return { wrapper, router };
    }

    it('renders documentation Indicator KPIs, status pills, and School Report links for Trust SEND Lead', async () => {
        const { wrapper } = await mountPage();

        expect(wrapper.find('[data-testid="trust-dashboard-page"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="trust-dashboard-disclaimer"]').text()).toContain(
            'not diagnoses, funding decisions, or statutory determinations',
        );
        expect(wrapper.text()).toContain('documentation Indicators');
        expect(wrapper.text()).not.toMatch(/AI found/i);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);

        const kpis = wrapper.findAll('[data-testid="kpi-card"]');
        expect(kpis).toHaveLength(5);
        expect(kpis[0].text()).toContain('Lateness');
        expect(kpis[0].find('[data-testid="kpi-value"]').text()).toContain('50%');
        expect(kpis[0].find('[data-testid="kpi-value"]').text()).toContain('1 of 2');
        expect(kpis[1].text()).toContain('Gap density');
        expect(kpis[1].find('[data-testid="kpi-value"]').text()).toContain('25%');
        expect(kpis[2].text()).toContain('Pupils in scope');
        expect(kpis[2].find('[data-testid="kpi-value"]').text()).toBe('4');
        expect(kpis[3].text()).toContain('Ready');
        expect(kpis[3].find('[data-testid="kpi-value"]').text()).toBe('1');
        expect(kpis[4].text()).toContain('Flagged Schools');
        expect(kpis[4].find('[data-testid="kpi-value"]').text()).toBe('0');
        expect(wrapper.find('[data-testid="trust-dashboard-escalations-empty"]').exists()).toBe(true);

        expect(wrapper.findAll('[data-testid="trust-dashboard-status-row"]')).toHaveLength(5);
        expect(wrapper.findAll('[data-testid="status-pill"]').length).toBeGreaterThan(0);
        expect(wrapper.find('[data-testid="trust-dashboard-empty"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="trust-dashboard-schools"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="trust-dashboard-school-link-sch_oak"]').text()).toContain('Oak Academy');
        expect(wrapper.find('[data-testid="trust-dashboard-school-link-sch_oak"]').attributes('href')).toContain(
            'school_id=sch_oak',
        );
        expect(wrapper.text()).not.toContain('Maya');
        expect(wrapper.find('a[href*="/pupils/"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-testid="trust-dashboard-school-escalations"]').at(0)?.text()).toBe('0');
    });

    it('cites escalation Rules without pupil names for Trust SEND Lead', async () => {
        const { wrapper } = await mountPage({
            payload: {
                ...leadPayload,
                escalated_pupils: 1,
                flagged_schools: 1,
                escalations: executivePayload.escalations,
                schools: [
                    {
                        ...leadPayload.schools[0],
                        escalated_pupils: 1,
                        flagged_schools: 1,
                        escalations: executivePayload.escalations,
                    },
                    leadPayload.schools[1],
                ],
            },
        });

        expect(wrapper.find('[data-testid="trust-dashboard-escalations-list"]').text()).toContain('ESC_SEQ_REVIEW');
        expect(wrapper.findAll('[data-testid="trust-dashboard-school-escalations"]').at(0)?.text()).toBe('1');
        expect(wrapper.findAll('[data-testid="trust-dashboard-school-escalations"]').at(1)?.text()).toBe('0');
        expect(wrapper.find('[data-testid="trust-dashboard-school-link-sch_oak"]').attributes('href')).toContain(
            'school_id=sch_oak',
        );
        expect(wrapper.text()).not.toContain('Maya');
        expect(wrapper.findAll('[data-testid="kpi-card"]').at(4)?.find('[data-testid="kpi-value"]').text()).toBe('1');
    });

    it('omits the School table for Trust Executive aggregates', async () => {
        const { wrapper } = await mountPage({ payload: executivePayload });

        expect(wrapper.find('[data-testid="trust-dashboard-kpis"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="trust-dashboard-schools"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="trust-dashboard-empty"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="trust-dashboard-escalations"]').text()).toContain('ESC_SEQ_REVIEW');
        expect(wrapper.find('[data-testid="trust-dashboard-escalations"]').text()).toContain(
            'Sequential Compliance — escalation review path',
        );
        expect(wrapper.find('a[href*="school_id"]').exists()).toBe(false);
    });

    it('shows FeatureFlaggedEmpty when the Trust Dashboard is not available for this Tenant', async () => {
        const { wrapper } = await mountPage({ status: 403, code: 'feature_not_available' });

        expect(wrapper.text()).toContain('Not available for this Tenant');
        expect(wrapper.find('[data-testid="trust-dashboard-kpis"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.text()).not.toContain('0%');
    });

    it('shows No Pilot data yet and zero counts when there are no enabled Schools', async () => {
        const { wrapper } = await mountPage({
            payload: {
                pupils_in_scope: 0,
                gaps: 0,
                gap_density: 0,
                lateness_rate: 0,
                overdue_open_cycles: 0,
                open_cycles: 0,
                by_status: {
                    ready: 0,
                    gaps: 0,
                    uncovered: 0,
                    'not-started': 0,
                    evaluating: 0,
                },
                escalated_pupils: 0,
                flagged_schools: 0,
                escalations: [],
                schools: [],
            },
        });

        expect(wrapper.find('[data-testid="trust-dashboard-empty"]').text()).toContain('No Pilot data yet.');
        expect(wrapper.find('[data-testid="trust-dashboard-schools"]').exists()).toBe(false);
        const values = wrapper.findAll('[data-testid="kpi-value"]').map((node) => node.text());
        expect(values[2]).toBe('0');
        expect(values[3]).toBe('0');
    });

    it('shows the School table without the empty card when enabled Schools have no Pupils', async () => {
        const { wrapper } = await mountPage({
            payload: {
                pupils_in_scope: 0,
                gaps: 0,
                gap_density: 0,
                lateness_rate: 0,
                overdue_open_cycles: 0,
                open_cycles: 0,
                by_status: {
                    ready: 0,
                    gaps: 0,
                    uncovered: 0,
                    'not-started': 0,
                    evaluating: 0,
                },
                schools: [
                    {
                        school_id: 'sch_oak',
                        name: 'Oak Academy',
                        pupils_in_scope: 0,
                        gaps: 0,
                        gap_density: 0,
                        lateness_rate: 0,
                        overdue_open_cycles: 0,
                        open_cycles: 0,
                        by_status: {
                            ready: 0,
                            gaps: 0,
                            uncovered: 0,
                            'not-started': 0,
                            evaluating: 0,
                        },
                    },
                    {
                        school_id: '   ',
                        name: 'Blank Id Academy',
                        pupils_in_scope: 0,
                        gaps: 0,
                        gap_density: 0,
                        lateness_rate: 0,
                        overdue_open_cycles: 0,
                        open_cycles: 0,
                        by_status: {
                            ready: 0,
                            gaps: 0,
                            uncovered: 0,
                            'not-started': 0,
                            evaluating: 0,
                        },
                    },
                ],
            },
        });

        expect(wrapper.find('[data-testid="trust-dashboard-empty"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="trust-dashboard-schools"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="trust-dashboard-school-link-sch_oak"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Blank Id Academy');
        expect(wrapper.find('a[href="/school-report"]').exists()).toBe(false);
    });

    it('shows access error for forbidden Trust Dashboard responses', async () => {
        const { wrapper } = await mountPage({ status: 403 });

        expect(wrapper.find('[data-testid="trust-dashboard-error"]').text()).toContain('You don’t have access.');
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
