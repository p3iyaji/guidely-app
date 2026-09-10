/** @vitest-environment jsdom */

import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import OntologyCataloguePage from '../pages/OntologyCataloguePage.vue';

const payloads = {
    '/api/v1/ontology/need-terms': [
        { id: 'need_1', code: 'CI', label: 'Communication and interaction' },
    ],
    '/api/v1/ontology/setting-terms': [
        { id: 'setting_1', code: 'CLASSROOM', label: 'Classroom' },
    ],
    '/api/v1/ontology/outcome-terms': [
        { id: 'outcome_1', code: 'ENGAGEMENT', label: 'Engagement' },
    ],
    '/api/v1/ontology/threshold-terms': [
        { id: 'threshold_1', code: 'INITIAL_REVIEW', label: 'Initial review' },
    ],
    '/api/v1/ontology/relationship-mappings': [
        {
            id: 'relationship_1',
            code: 'CI_TO_UNIVERSAL',
            label: 'Communication need to universal strategies',
            relationship_type: 'need_to_provision',
            from_term: { id: 'need_1', type: 'need', label: 'Communication and interaction' },
            to_term: { id: 'provision_1', type: 'provision', label: 'Universal strategies' },
        },
    ],
    '/api/v1/ontology/rules': [
        {
            id: 'rule_1',
            code: 'REV_THR_EVID',
            label: 'Evidential Sufficiency review threshold',
            dimension: 'evidential_sufficiency',
            category: 'review_threshold',
            evaluation: { type: 'review_threshold_check' },
            outcome: { result: 'review_required', gap_code: 'REV_THR' },
        },
    ],
};

describe('OntologyCataloguePage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url) => jsonResponse({
            data: payloads[String(url)] ?? [],
        }));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    async function mountPage() {
        const wrapper = mount(OntologyCataloguePage, {
            global: {
                stubs: { RouterLink: RouterLinkStub },
            },
        });
        await flushPromises();

        return wrapper;
    }

    it('loads every catalogue endpoint and links to Provision term management', async () => {
        const wrapper = await mountPage();

        expect(fetchMock.mock.calls.map(([url]) => String(url))).toEqual(Object.keys(payloads));
        expect(wrapper.find('[data-testid="ontology-catalogue-page"]').exists()).toBe(true);
        expect(wrapper.findComponent(RouterLinkStub).props('to')).toBe('/provision-terms');
        expect(wrapper.find('[role="tablist"]').attributes('aria-label')).toBe('Catalogue sections');
        expect(wrapper.findAll('[role="tab"]')).toHaveLength(6);
        expect(wrapper.text()).toContain('Communication and interaction');
    });

    it('shows meaningful Relationship and Rule fields without raw JSON', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="ontology-tab-relationships"]').trigger('click');
        expect(wrapper.text()).toContain('Communication and interaction · Need');
        expect(wrapper.text()).toContain('Universal strategies · Provision');
        expect(wrapper.text()).toContain('Need To Provision');

        await wrapper.find('[data-testid="ontology-tab-rules"]').trigger('click');
        expect(wrapper.text()).toContain('Evidential Sufficiency');
        expect(wrapper.text()).toContain('Review Threshold');
        expect(wrapper.text()).toContain('Review Threshold Check');
        expect(wrapper.text()).toContain('Review Required');
        expect(wrapper.text()).toContain('REV_THR');
        expect(wrapper.text()).not.toContain('{"');
    });

    it('filters the active section by code or label', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="ontology-catalogue-search"]').setValue('missing');
        expect(wrapper.find('[data-testid="ontology-needs-search-empty"]').exists()).toBe(true);

        await wrapper.find('[data-testid="ontology-catalogue-search"]').setValue('ci');
        expect(wrapper.findAll('[data-testid="ontology-needs-card"]')).toHaveLength(1);
    });

    it('keeps successful sections available when one endpoint fails', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url) === '/api/v1/ontology/outcome-terms') {
                return jsonResponse({}, 503);
            }

            return jsonResponse({ data: payloads[String(url)] ?? [] });
        });

        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Communication and interaction');
        await wrapper.find('[data-testid="ontology-tab-outcomes"]').trigger('click');
        expect(wrapper.find('[data-testid="ontology-outcomes-error"]').attributes('role')).toBe('alert');
        expect(wrapper.text()).toContain('Unable to load outcome catalogue.');
    });

    it('shows an honest source empty state', async () => {
        fetchMock.mockImplementation(async (url) => jsonResponse({
            data: String(url) === '/api/v1/ontology/threshold-terms'
                ? []
                : (payloads[String(url)] ?? []),
        }));

        const wrapper = await mountPage();
        await wrapper.find('[data-testid="ontology-tab-thresholds"]').trigger('click');

        expect(wrapper.find('[data-testid="ontology-thresholds-empty"]').text()).toContain(
            'No Threshold terms are available on the active version.',
        );
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
