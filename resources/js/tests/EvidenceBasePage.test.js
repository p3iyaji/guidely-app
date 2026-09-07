/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useSession } from '../features/auth/session.js';
import EvidenceBasePage from '../pages/EvidenceBasePage.vue';

const pupil = {
    id: 'pup_1',
    given_name: 'Maya',
    family_name: 'Okonkwo',
    year_group: 'Year 4',
    send_status: 'sen_support',
};

const sampleRecords = [
    {
        id: 'ev_1',
        type: 'observation',
        source: null,
        occurred_at: '2026-09-06T10:00:00+00:00',
        body: 'Settled after the visual timetable.',
        author: { id: 'usr_1', name: 'Alex Teacher' },
        setting: { id: 'set_1', code: 'CLASSROOM', label: 'Classroom' },
    },
    {
        id: 'ev_2',
        type: 'intervention',
        source: 'import',
        occurred_at: '2026-09-05T09:00:00+00:00',
        body: 'Imported provision row',
        author: { id: 'usr_2', name: 'Import Bot' },
        provision: { id: 'prv_1', code: 'SMALL_GROUP', label: 'Small group' },
    },
];

describe('EvidenceBasePage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url) => {
            const path = String(url);

            if (path.match(/\/api\/v1\/pupils\/[^/?]+$/) && !path.includes('/evidence')) {
                return jsonResponse({ data: pupil });
            }

            if (path.includes('/evidence')) {
                if (path.includes('filter=review_note')) {
                    return jsonResponse({ data: [] });
                }

                if (path.includes('filter=import')) {
                    return jsonResponse({ data: [sampleRecords[1]] });
                }

                if (path.includes('filter=observation')) {
                    return jsonResponse({ data: [sampleRecords[0]] });
                }

                return jsonResponse({ data: sampleRecords });
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
     * @param {{ evidence?: unknown[], pupilOverride?: unknown }} [options]
     */
    async function mountPage(role = 'senco', options = {}) {
        const { evidence, pupilOverride } = options;

        if (evidence !== undefined || pupilOverride !== undefined) {
            fetchMock.mockImplementation(async (url) => {
                const path = String(url);

                if (path.match(/\/api\/v1\/pupils\/[^/?]+$/) && !path.includes('/evidence')) {
                    return jsonResponse({ data: pupilOverride === undefined ? pupil : pupilOverride });
                }

                if (path.includes('/evidence')) {
                    return jsonResponse({ data: evidence ?? [] });
                }

                return jsonResponse({});
            });
        }

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
                {
                    path: '/pupils/:id',
                    name: 'pupil-detail',
                    component: EvidenceBasePage,
                    meta: { title: 'Evidence Base' },
                },
                { path: '/capture', name: 'capture', component: { template: '<div>capture</div>' } },
            ],
        });

        await router.push('/pupils/pup_1');
        await router.isReady();

        const wrapper = mount(EvidenceBasePage, {
            global: {
                plugins: [router],
            },
        });

        await flushPromises();

        return { wrapper, router };
    }

    it('shows FR-35 disclaimer, filters, and chronological list', async () => {
        const { wrapper } = await mountPage('senco');

        expect(wrapper.find('[data-testid="evidence-base-page"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-base-title"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="evidence-base-disclaimer"]').text()).toContain(
            'Documentation evaluations support professional judgement',
        );
        expect(wrapper.find('[data-testid="evidence-base-disclaimer"]').text()).toContain(
            'not diagnoses, funding decisions, or statutory determinations',
        );
        expect(wrapper.find('[data-testid="evidence-base-filters"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-filter-observation"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-filter-review_note"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-filter-import"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="evidence-row"]').length).toBe(2);
        expect(wrapper.find('[data-testid="evidence-type"]').text()).toContain('Observation');
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.text()).not.toMatch(/Open Gaps/i);
        expect(wrapper.text()).not.toMatch(/Last SRE run/i);
    });

    it('filters to import and review note (empty until 4.3)', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="evidence-filter-import"]').trigger('click');
        await flushPromises();

        expect(fetchMock.mock.calls.some(([url]) => String(url).includes('filter=import'))).toBe(true);
        expect(wrapper.findAll('[data-testid="evidence-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="evidence-type"]').text()).toContain('Import');

        await wrapper.find('[data-testid="evidence-filter-review_note"]').trigger('click');
        await flushPromises();

        expect(fetchMock.mock.calls.some(([url]) => String(url).includes('filter=review_note'))).toBe(true);
        expect(wrapper.find('[data-testid="evidence-base-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-base-empty-copy"]').text())
            .toContain('No Evidence Records match this filter.');
        expect(wrapper.find('[data-testid="evidence-base-capture-cta"]').exists()).toBe(false);
    });

    it('shows Capture CTA for Teacher empty state and hides it for School Leader', async () => {
        const teacher = await mountPage('teacher', { evidence: [] });
        expect(teacher.wrapper.find('[data-testid="evidence-base-empty-copy"]').text())
            .toContain('No Evidence Records yet.');
        expect(teacher.wrapper.find('[data-testid="evidence-base-capture-cta"]').exists()).toBe(true);

        await teacher.wrapper.find('[data-testid="evidence-base-capture-cta"]').trigger('click');
        await flushPromises();
        expect(teacher.router.currentRoute.value.name).toBe('capture');
        teacher.wrapper.unmount();

        const leader = await mountPage('school_leader', { evidence: [] });
        expect(leader.wrapper.find('[data-testid="evidence-base-empty"]').exists()).toBe(true);
        expect(leader.wrapper.find('[data-testid="evidence-base-capture-cta"]').exists()).toBe(false);
        leader.wrapper.unmount();
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
        clone() {
            return this;
        },
        async json() {
            return body;
        },
    };
}
