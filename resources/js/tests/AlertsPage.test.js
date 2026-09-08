/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
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

function mountPage() {
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
});
