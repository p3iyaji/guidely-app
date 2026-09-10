/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useSession } from '../features/auth/session.js';
import AlertsPage from '../pages/AlertsPage.vue';

function jsonResponse(body, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        clone() {
            return this;
        },
        async json() {
            return body;
        },
    };
}

function mountPage(role = 'senco') {
    useSession().setUser({
        id: 'usr_1',
        name: 'Ada User',
        email: 'ada@example.com',
        role,
        tenant_id: 'ten_1',
    });

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component: { template: '<div />' } },
            { path: '/alerts', component: AlertsPage },
            { path: '/school-report', component: { template: '<div />' } },
        ],
    });

    return mount(AlertsPage, {
        global: {
            plugins: [router],
        },
    });
}

describe('AlertsPage', () => {
    beforeEach(() => {
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

    it('shows FeatureFlaggedEmpty when compliance alerts are not available for this Tenant', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({
                message: 'This feature is not available for this Tenant.',
                code: 'feature_not_available',
                feature: 'compliance_alerts',
            }, 403)),
        );

        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.find('[data-testid="alerts-page"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Not available for this Tenant');
        expect(wrapper.find('[data-testid="alerts-list"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
    });

    it('shows empty copy when there are no open Indicator alerts', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({ data: [] })),
        );

        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('No open Indicator alerts.');
        expect(wrapper.find('[data-testid="alerts-disclaimer"]').text()).toContain('not diagnoses');
    });

    it('lists school alerts with a School Report drill-down and no pupil names', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({
                data: [
                    {
                        id: 'alert_1',
                        scope: 'school',
                        school_id: 'sch_oak',
                        school_name: 'Oak Academy',
                        metric: 'gap_density',
                        metric_label: 'Gap density',
                        citation: 'Gap density Indicator: observed 100% meets configured threshold 50%.',
                    },
                ],
            })),
        );

        const wrapper = mountPage();
        await flushPromises();

        const link = wrapper.find('[data-testid="alerts-school-link-sch_oak"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toContain('/school-report');
        expect(link.attributes('href')).toContain('school_id=sch_oak');
        expect(wrapper.text()).toContain('Gap density Indicator: observed 100% meets configured threshold 50%.');
        expect(wrapper.text()).not.toContain('Maya');
        expect(wrapper.text()).not.toMatch(/AI found/i);
    });

    it('loads and saves Trust thresholds as human-readable percentages for a Tenant Admin', async () => {
        const fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = String(options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/compliance-alert-thresholds') && method === 'GET') {
                return jsonResponse({
                    data: [
                        { id: 'thr_gap', school_id: null, metric: 'gap_density', threshold: 0.4 },
                        { id: 'thr_late', school_id: null, metric: 'lateness_rate', threshold: 0.125 },
                    ],
                });
            }

            if (path.includes('/api/v1/compliance-alert-thresholds') && method === 'PATCH') {
                return jsonResponse({ data: JSON.parse(String(options.body ?? '{}')) });
            }

            return jsonResponse({ data: [] });
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountPage('tenant_admin');
        await flushPromises();

        expect(wrapper.find('[data-testid="threshold-gap_density"]').element.value).toBe('40');
        expect(wrapper.find('[data-testid="threshold-lateness_rate"]').element.value).toBe('12.5');
        expect(fetchMock.mock.calls.some((call) => String(call[0]).includes('/api/v1/compliance-alerts'))).toBe(false);

        await wrapper.find('[data-testid="threshold-gap_density"]').setValue('65');
        await wrapper.find('[data-testid="threshold-lateness_rate"]').setValue('20.5');
        await wrapper.find('[data-testid="threshold-form"]').trigger('submit');
        await flushPromises();

        const patchBodies = fetchMock.mock.calls
            .filter((call) => String(call[0]).includes('/api/v1/compliance-alert-thresholds')
                && String(call[1]?.method ?? 'GET').toUpperCase() === 'PATCH')
            .map((call) => JSON.parse(String(call[1].body)));

        expect(patchBodies).toEqual([
            { school_id: null, metric: 'gap_density', threshold: 0.65 },
            { school_id: null, metric: 'lateness_rate', threshold: 0.205 },
        ]);
        expect(wrapper.find('[data-testid="thresholds-success"]').text()).toBe('Alert thresholds saved.');
    });

    it('does not request thresholds for a non-admin alert viewer', async () => {
        const fetchMock = vi.fn(async () => jsonResponse({ data: [] }));
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountPage('trust_send_lead');
        await flushPromises();

        expect(wrapper.find('[data-testid="threshold-manager"]').exists()).toBe(false);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0][0])).toContain('/api/v1/compliance-alerts');
    });

    it('shows a per-field API validation error while retaining threshold values', async () => {
        const fetchMock = vi.fn(async (url, options = {}) => {
            const method = String(options.method ?? 'GET').toUpperCase();

            if (method === 'PATCH') {
                return jsonResponse({
                    message: 'The threshold field must be between 0 and 1.',
                    errors: {
                        threshold: ['The threshold field must be between 0 and 1.'],
                    },
                }, 422);
            }

            return jsonResponse({ data: [] });
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountPage('tenant_admin');
        await flushPromises();
        await wrapper.find('[data-testid="threshold-gap_density"]').setValue('75');
        await wrapper.find('[data-testid="threshold-form"]').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="threshold-error-gap_density"]').text())
            .toBe('The threshold field must be between 0 and 1.');
        expect(wrapper.find('[data-testid="thresholds-save-error"]').text())
            .toBe('The threshold field must be between 0 and 1.');
        expect(wrapper.find('[data-testid="threshold-gap_density"]').element.value).toBe('75');
    });
});
