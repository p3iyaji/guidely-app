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
        lifecycle: 'submitted',
        source: null,
        pupil_id: 'pup_1',
        author_id: 'usr_1',
        occurred_at: '2026-09-06T10:00:00+00:00',
        body: 'Settled after the visual timetable.',
        author: { id: 'usr_1', name: 'Alex Teacher' },
        setting: { id: 'set_1', code: 'CLASSROOM', label: 'Classroom' },
    },
    {
        id: 'ev_2',
        type: 'intervention',
        lifecycle: 'submitted',
        source: 'import',
        pupil_id: 'pup_1',
        author_id: 'usr_2',
        occurred_at: '2026-09-05T09:00:00+00:00',
        body: 'Imported provision row',
        author: { id: 'usr_2', name: 'Import Bot' },
        provision: { id: 'prv_1', code: 'SMALL_GROUP', label: 'Small group' },
    },
    {
        id: 'ev_3',
        type: 'review_note',
        lifecycle: 'submitted',
        source: 'capture',
        pupil_id: 'pup_1',
        author_id: 'usr_senco',
        occurred_at: '2026-09-04T14:00:00+00:00',
        body: 'SENCO review commentary on documentation sufficiency.',
        author: { id: 'usr_senco', name: 'Sam SENCO' },
    },
];

const settingTerms = [
    { id: 'set_1', code: 'CLASSROOM', label: 'Classroom' },
    { id: 'set_2', code: 'PLAYGROUND', label: 'Playground' },
];

const provisionTerms = [
    { id: 'prv_1', code: 'SMALL_GROUP', label: 'Small group' },
];

const sampleDeterminations = [
    {
        id: 'det_1',
        dimension: 'Sequential Compliance',
        result: 'unmet',
        result_label: 'Not met',
        rule: { id: 'rule_1', code: 'SEQ_01', label: 'Sequential documentation threshold' },
        rule_library_version: {
            id: 'rlv_1',
            code: 'pilot-rule-library-stub-v1',
            label: 'Pilot Rule Library stub v1 (partial coverage)',
        },
        reasoning_pathway: {
            rule: { id: 'rule_1', code: 'SEQ_01', label: 'Sequential documentation threshold' },
            condition_steps: [
                { type: 'evidence_count', status: 'passed', detail: 'Has submitted Evidence' },
            ],
            evaluation_steps: [
                { type: 'threshold', status: 'failed', detail: 'Below documentation threshold' },
            ],
            evidence_ids: ['ev_1'],
            result: 'unmet',
        },
        is_current: true,
    },
    {
        id: 'det_2',
        dimension: 'Outcome Progression',
        result: 'uncovered',
        result_label: 'Uncovered',
        rule: null,
        rule_library_version: {
            id: 'rlv_1',
            code: 'pilot-rule-library-stub-v1',
            label: 'Pilot Rule Library stub v1 (partial coverage)',
        },
        reasoning_pathway: {
            rule: null,
            condition_steps: [
                { type: 'applicable_rule', status: 'failed', detail: 'No applicable Rule for this dimension.' },
            ],
            evaluation_steps: [],
            evidence_ids: [],
            result: 'uncovered',
        },
        is_current: true,
    },
];

describe('EvidenceBasePage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = String(options.method ?? 'GET').toUpperCase();

            if (path.match(/\/api\/v1\/pupils\/[^/?]+$/) && !path.includes('/evidence') && !path.includes('/interventions') && !path.includes('/determinations')) {
                return jsonResponse({ data: pupil });
            }

            if (path.includes('/determinations')) {
                return jsonResponse({ data: sampleDeterminations });
            }

            if (path.includes('/ontology/setting-terms')) {
                return jsonResponse({ data: settingTerms });
            }

            if (path.includes('/ontology/provision-terms')) {
                return jsonResponse({ data: provisionTerms });
            }

            if (path.includes('/versions')) {
                return jsonResponse({
                    data: [
                        {
                            id: 'ver_1',
                            version: 1,
                            snapshot: { body: 'Prior observation body' },
                            superseded_at: '2026-09-06T09:00:00+00:00',
                            superseded_by: { id: 'usr_1', name: 'Alex Teacher' },
                        },
                    ],
                });
            }

            if (method === 'PATCH' && path.includes('/evidence/')) {
                const body = JSON.parse(String(options.body ?? '{}'));

                return jsonResponse({
                    data: {
                        ...sampleRecords[0],
                        body: body.body ?? sampleRecords[0].body,
                        setting: settingTerms.find((term) => term.id === body.setting_term_id) ?? sampleRecords[0].setting,
                    },
                });
            }

            if (method === 'POST' && path.includes('/review-notes')) {
                const body = JSON.parse(String(options.body ?? '{}'));

                return jsonResponse({
                    data: {
                        id: 'ev_review_1',
                        type: 'review_note',
                        lifecycle: 'submitted',
                        source: 'capture',
                        pupil_id: 'pup_1',
                        author_id: 'usr_1',
                        occurred_at: body.occurred_at ?? '2026-09-07T10:00:00+00:00',
                        body: body.body ?? '',
                        author: { id: 'usr_1', name: 'Test User' },
                    },
                }, 201);
            }

            if (path.includes('/evidence')) {
                if (path.includes('filter=review_note')) {
                    return jsonResponse({ data: [sampleRecords[2]] });
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
     * @param {{ evidence?: unknown[], pupilOverride?: unknown, userId?: string, path?: string }} [options]
     */
    async function mountPage(role = 'senco', options = {}) {
        const { evidence, pupilOverride, userId = 'usr_1', path = '/pupils/pup_1' } = options;

        if (evidence !== undefined || pupilOverride !== undefined) {
            fetchMock.mockImplementation(async (url, requestOptions = {}) => {
                const path = String(url);
                const method = String(requestOptions.method ?? 'GET').toUpperCase();

                if (path.match(/\/api\/v1\/pupils\/[^/?]+$/) && !path.includes('/evidence') && !path.includes('/interventions') && !path.includes('/determinations')) {
                    return jsonResponse({ data: pupilOverride === undefined ? pupil : pupilOverride });
                }

                if (path.includes('/determinations')) {
                    return jsonResponse({ data: sampleDeterminations });
                }

                if (path.includes('/ontology/setting-terms')) {
                    return jsonResponse({ data: settingTerms });
                }

                if (path.includes('/ontology/provision-terms')) {
                    return jsonResponse({ data: provisionTerms });
                }

                if (path.includes('/versions')) {
                    return jsonResponse({ data: [] });
                }

                if (method === 'PATCH' && path.includes('/evidence/')) {
                    return jsonResponse({ data: sampleRecords[0] });
                }

                if (path.includes('/evidence')) {
                    return jsonResponse({ data: evidence ?? [] });
                }

                return jsonResponse({});
            });
        }

        useSession().setUser({
            id: userId,
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

        await router.push(path);
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
        expect(wrapper.findAll('[data-testid="evidence-row"]').length).toBe(3);
        expect(wrapper.find('[data-testid="evidence-type"]').text()).toContain('Observation');
        expect(wrapper.find('[data-testid="evidence-review-note-open"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-determinations"]').exists()).toBe(true);
        expect(fetchMock.mock.calls.some(([url]) => (
            String(url).includes('/determinations') && String(url).includes('current=1')
        ))).toBe(true);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.text()).not.toMatch(/Last SRE run/i);
        expect(wrapper.text().toLowerCase()).not.toContain('confidence');
    });

    it('expands Reasoning Pathway with Rule, conditions, Evidence links, and Uncovered label', async () => {
        const { wrapper } = await mountPage('senco');

        expect(wrapper.find('[data-testid="evidence-determinations-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="determination-override-placeholder"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="determination-override-placeholder"]').attributes('disabled')).toBeDefined();

        const uncoveredResults = wrapper.findAll('[data-testid="determination-result"]')
            .filter((node) => node.text().includes('Uncovered'));
        expect(uncoveredResults.length).toBeGreaterThan(0);
        expect(uncoveredResults[0].classes().join(' ')).toContain('bg-danger-soft');
        expect(uncoveredResults[0].classes().join(' ')).not.toContain('bg-success-soft');

        await wrapper.find('[data-testid="determination-toggle-det_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="determination-pathway"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pathway-rule-code"]').text()).toContain('SEQ_01');
        expect(wrapper.find('[data-testid="pathway-rule-library"]').text())
            .toContain('Pilot Rule Library stub v1');
        expect(wrapper.find('[data-testid="pathway-condition-step"]').text())
            .toContain('Has submitted Evidence');
        expect(wrapper.find('[data-testid="pathway-evaluation-step"]').text())
            .toContain('Below documentation threshold');
        expect(wrapper.find('[data-testid="pathway-evidence-link"]').attributes('href'))
            .toBe('#evidence-ev_1');
        expect(wrapper.find('#evidence-ev_1').exists()).toBe(true);
        expect(wrapper.find('[data-testid="determination-toggle-det_1"]').text())
            .toContain('Sequential Compliance');
        expect(wrapper.find('[data-testid="determination-toggle-det_1"]').attributes('aria-controls'))
            .toBe('determination-pathway-det_1');
        expect(wrapper.find('[data-testid="pathway-result-pill"]').text()).toContain('Not met');
        expect(wrapper.text().toLowerCase()).not.toContain('confidence');
        expect(wrapper.find('[data-testid="evidence-base-disclaimer"]').text()).toContain('not diagnoses');
    });

    it('expands Reasoning Pathway from a Gap deep-link without a toggle click', async () => {
        const { wrapper } = await mountPage('senco', {
            path: '/pupils/pup_1?focus=determination&determination=det_1',
        });

        expect(wrapper.find('[data-testid="determination-pathway"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="determination-toggle-det_1"]').attributes('aria-expanded'))
            .toBe('true');
        expect(wrapper.find('[data-testid="pathway-rule-code"]').text()).toContain('SEQ_01');
    });

    it('re-applies pathway focus when the determination query changes on the same Pupil', async () => {
        const { wrapper, router } = await mountPage('senco', {
            path: '/pupils/pup_1?focus=determination&determination=det_1',
        });

        expect(wrapper.find('[data-testid="pathway-rule-code"]').text()).toContain('SEQ_01');

        await router.push('/pupils/pup_1?focus=determination&determination=det_2');
        await flushPromises();

        expect(wrapper.find('[data-testid="pathway-result-pill"]').text()).toContain('Uncovered');
        expect(wrapper.find('[data-testid="determination-toggle-det_2"]').attributes('aria-expanded'))
            .toBe('true');
    });

    it('shows Determinations error when the current list fails to load', async () => {
        const original = fetchMock.getMockImplementation();
        fetchMock.mockImplementation(async (url, options = {}) => {
            if (String(url).includes('/determinations')) {
                return jsonResponse({}, 500);
            }

            return original(url, options);
        });

        const { wrapper } = await mountPage('senco');

        expect(wrapper.find('[data-testid="evidence-determinations-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-determinations-error"]').text())
            .toContain('Unable to load Determinations');
        expect(wrapper.find('[data-testid="evidence-determinations-empty"]').exists()).toBe(false);
    });

    it('shows read-only pathway for School Leader without Override CTA', async () => {
        const { wrapper } = await mountPage('school_leader');

        expect(wrapper.find('[data-testid="evidence-determinations"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="determination-override-placeholder"]').exists()).toBe(false);

        await wrapper.find('[data-testid="determination-toggle-det_2"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="pathway-result-pill"]').text()).toContain('Uncovered');
        expect(wrapper.find('[data-testid="pathway-result-pill"]').classes().join(' '))
            .toContain('bg-danger-soft');
    });

    it('hides Determinations panel for Teacher and Support Staff', async () => {
        const teacher = await mountPage('teacher');

        expect(teacher.wrapper.find('[data-testid="evidence-determinations"]').exists()).toBe(false);
        expect(fetchMock.mock.calls.some(([url]) => String(url).includes('/determinations'))).toBe(false);
        teacher.wrapper.unmount();

        fetchMock.mockClear();
        const support = await mountPage('support_staff');

        expect(support.wrapper.find('[data-testid="evidence-determinations"]').exists()).toBe(false);
        expect(fetchMock.mock.calls.some(([url]) => String(url).includes('/determinations'))).toBe(false);
    });

    it('filters to import and review note with labelled rows', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="evidence-filter-import"]').trigger('click');
        await flushPromises();

        expect(fetchMock.mock.calls.some(([url]) => String(url).includes('filter=import'))).toBe(true);
        expect(wrapper.findAll('[data-testid="evidence-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="evidence-type"]').text()).toContain('Import');

        await wrapper.find('[data-testid="evidence-filter-review_note"]').trigger('click');
        await flushPromises();

        expect(fetchMock.mock.calls.some(([url]) => String(url).includes('filter=review_note'))).toBe(true);
        expect(wrapper.findAll('[data-testid="evidence-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="evidence-type"]').text()).toContain('Review note');
        expect(wrapper.find('[data-testid="evidence-body"]').text())
            .toContain('SENCO review commentary on documentation sufficiency.');
        expect(wrapper.find('[data-testid="evidence-base-capture-cta"]').exists()).toBe(false);
    });

    it('lets SENCO create a review note and hides the control for Teacher and School Leader', async () => {
        const senco = await mountPage('senco');

        expect(senco.wrapper.find('[data-testid="evidence-review-note-open"]').exists()).toBe(true);
        await senco.wrapper.find('[data-testid="evidence-review-note-open"]').trigger('click');
        await flushPromises();

        expect(senco.wrapper.find('[data-testid="evidence-review-note-panel"]').exists()).toBe(true);
        await senco.wrapper.find('[data-testid="evidence-review-note-body"]')
            .setValue('Fresh SENCO professional commentary.');
        await senco.wrapper.find('[data-testid="evidence-review-note-panel"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(([url, options]) => (
            String(url).includes('/api/v1/review-notes')
            && String(options?.method ?? '').toUpperCase() === 'POST'
        ));

        expect(postCall).toBeTruthy();
        const body = JSON.parse(String(postCall[1].body));
        expect(body.pupil_id).toBe('pup_1');
        expect(body.body).toBe('Fresh SENCO professional commentary.');
        expect(body.occurred_at).toBeTruthy();
        expect(body).not.toHaveProperty('setting_term_id');
        expect(body).not.toHaveProperty('lifecycle');
        expect(senco.wrapper.find('[data-testid="evidence-review-note-panel"]').exists()).toBe(false);

        const postIndex = fetchMock.mock.calls.findIndex(([url, options]) => (
            String(url).includes('/api/v1/review-notes')
            && String(options?.method ?? '').toUpperCase() === 'POST'
        ));
        expect(postIndex).toBeGreaterThanOrEqual(0);

        const reloadAfterPost = fetchMock.mock.calls.slice(postIndex + 1).find(([url, options]) => {
            const path = String(url);
            const method = String(options?.method ?? 'GET').toUpperCase();

            return method === 'GET'
                && /\/api\/v1\/pupils\/pup_1\/evidence(?:\?|$)/.test(path)
                && !path.includes('filter=');
        });

        expect(reloadAfterPost).toBeTruthy();
        senco.wrapper.unmount();

        const teacher = await mountPage('teacher');
        expect(teacher.wrapper.find('[data-testid="evidence-review-note-open"]').exists()).toBe(false);
        expect(teacher.wrapper.find('[data-testid="evidence-review-note-section"]').exists()).toBe(false);
        teacher.wrapper.unmount();

        const leader = await mountPage('school_leader');
        expect(leader.wrapper.find('[data-testid="evidence-review-note-open"]').exists()).toBe(false);
        expect(leader.wrapper.find('[data-testid="evidence-review-note-section"]').exists()).toBe(false);
        leader.wrapper.unmount();
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

    it('shows Amend for author or SENCO and hides it for School Leader', async () => {
        const senco = await mountPage('senco');
        expect(senco.wrapper.findAll('[data-testid="evidence-amend-open"]').length).toBe(2);
        senco.wrapper.unmount();

        const author = await mountPage('teacher', { userId: 'usr_1' });
        expect(author.wrapper.findAll('[data-testid="evidence-amend-open"]').length).toBe(1);
        author.wrapper.unmount();

        const support = await mountPage('support_staff', { userId: 'usr_1' });
        expect(support.wrapper.findAll('[data-testid="evidence-amend-open"]').length).toBe(1);
        support.wrapper.unmount();

        const leader = await mountPage('school_leader');
        expect(leader.wrapper.find('[data-testid="evidence-amend-open"]').exists()).toBe(false);
        leader.wrapper.unmount();
    });

    it('opens amend form with previous versions and saves a correction', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="evidence-amend-open"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="evidence-amend-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-versions-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="evidence-version-body"]').text())
            .toContain('Prior observation body');
        expect(fetchMock.mock.calls.some(([url]) => String(url).includes('/versions'))).toBe(true);

        await wrapper.find('[data-testid="evidence-amend-body"]').setValue('Corrected observation notes');
        await wrapper.find('[data-testid="evidence-amend-setting"]').setValue('set_2');
        await wrapper.find('[data-testid="evidence-amend-panel"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(([url, options]) => (
            String(url).includes('/api/v1/evidence/ev_1')
            && String(options?.method ?? '').toUpperCase() === 'PATCH'
        ));

        expect(patchCall).toBeTruthy();
        const body = JSON.parse(String(patchCall[1].body));
        expect(body.body).toBe('Corrected observation notes');
        expect(body.setting_term_id).toBe('set_2');
        expect(body).not.toHaveProperty('type');
        expect(body).not.toHaveProperty('pupil_id');
        expect(wrapper.find('[data-testid="evidence-amend-panel"]').exists()).toBe(false);
    });

    it('sends provision_term_id when amending an Intervention as author', async () => {
        const authoredIntervention = {
            ...sampleRecords[1],
            author_id: 'usr_1',
            author: { id: 'usr_1', name: 'Alex Teacher' },
            source: null,
        };

        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = String(options.method ?? 'GET').toUpperCase();

            if (path.match(/\/api\/v1\/pupils\/[^/?]+$/) && !path.includes('/evidence') && !path.includes('/interventions') && !path.includes('/determinations')) {
                return jsonResponse({ data: pupil });
            }

            if (path.includes('/determinations')) {
                return jsonResponse({ data: sampleDeterminations });
            }

            if (path.includes('/ontology/setting-terms')) {
                return jsonResponse({ data: settingTerms });
            }

            if (path.includes('/ontology/provision-terms')) {
                return jsonResponse({ data: provisionTerms });
            }

            if (path.includes('/versions')) {
                return jsonResponse({ data: [] });
            }

            if (method === 'PATCH' && path.includes('/evidence/')) {
                const body = JSON.parse(String(options.body ?? '{}'));

                return jsonResponse({
                    data: {
                        ...authoredIntervention,
                        body: body.body ?? authoredIntervention.body,
                        provision: provisionTerms.find((term) => term.id === body.provision_term_id)
                            ?? authoredIntervention.provision,
                    },
                });
            }

            if (path.includes('/evidence')) {
                return jsonResponse({ data: [authoredIntervention] });
            }

            return jsonResponse({});
        });

        const { wrapper } = await mountPage('teacher', { userId: 'usr_1' });

        await wrapper.find('[data-testid="evidence-amend-open"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="evidence-amend-provision"]').exists()).toBe(true);
        await wrapper.find('[data-testid="evidence-amend-provision"]').setValue('prv_1');
        await wrapper.find('[data-testid="evidence-amend-body"]').setValue('Updated intervention notes');
        await wrapper.find('[data-testid="evidence-amend-panel"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(([url, options]) => (
            String(url).includes('/api/v1/evidence/ev_2')
            && String(options?.method ?? '').toUpperCase() === 'PATCH'
        ));

        expect(patchCall).toBeTruthy();
        const body = JSON.parse(String(patchCall[1].body));
        expect(body.provision_term_id).toBe('prv_1');
        expect(body.body).toBe('Updated intervention notes');
        expect(wrapper.find('[data-testid="evidence-amend-panel"]').exists()).toBe(false);
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
