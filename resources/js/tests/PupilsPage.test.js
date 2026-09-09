/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useSession } from '../features/auth/session.js';
import PupilsPage from '../pages/PupilsPage.vue';
import StatusPill from '../shared/ui/StatusPill.vue';

const samplePupils = [
    {
        id: 'pup_1',
        given_name: 'Alex',
        family_name: 'Rivera',
        year_group: 'Year 8',
        documentation_status: 'not-started',
    },
    {
        id: 'pup_2',
        given_name: 'Jordan',
        family_name: 'Lee',
        year_group: 'Year 9',
        documentation_status: 'gaps',
    },
];

describe('StatusPill', () => {
    it('renders text and non-empty icon for documented statuses including evaluating', () => {
        const gaps = mount(StatusPill, { props: { status: 'gaps' } });
        expect(gaps.text()).toContain('Gaps');
        const gapsIcon = gaps.find('[data-testid="status-pill-icon"]');
        expect(gapsIcon.exists()).toBe(true);
        expect(gapsIcon.text().trim().length).toBeGreaterThan(0);
        expect(gaps.find('[data-testid="status-pill-spinner"]').exists()).toBe(false);

        const evaluating = mount(StatusPill, { props: { status: 'evaluating' } });
        expect(evaluating.text()).toContain('Evaluating');
        const evaluatingIcon = evaluating.find('[data-testid="status-pill-icon"]');
        expect(evaluatingIcon.exists()).toBe(true);
        expect(evaluatingIcon.text().trim().length).toBeGreaterThan(0);
        expect(evaluatingIcon.classes()).not.toContain('animate-spin');
        expect(evaluating.find('[data-testid="status-pill-spinner"]').exists()).toBe(true);
    });
});

describe('PupilsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/pupils') && method === 'GET' && !path.match(/\/pupils\/[^/?]+$/)) {
                return jsonResponse({ data: samplePupils });
            }

            if (path.includes('/ontology/need-terms')) {
                return jsonResponse({
                    data: [
                        { id: 'need_ci', code: 'CI', label: 'Communication and interaction' },
                        { id: 'need_cl', code: 'CL', label: 'Cognition and learning' },
                    ],
                });
            }

            if (path.includes('/assignable-staff')) {
                return jsonResponse({
                    data: [
                        { id: 11, name: 'Alex Teacher', role: 'teacher' },
                        { id: 12, name: 'Pat Support', role: 'support_staff' },
                    ],
                });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({
                    data: [{ id: 'sch_1', name: 'Northbridge Primary', is_active: true }],
                });
            }

            if (path.includes('/assignments') && method === 'POST') {
                return jsonResponse({
                    data: {
                        id: 'pup_new',
                        assigned_staff: [{ id: 11, name: 'Alex Teacher', role: 'teacher' }],
                    },
                });
            }

            if (path.includes('/api/v1/pupils') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'pup_new',
                            given_name: 'Sam',
                            family_name: 'Patel',
                            year_group: 'Year 7',
                            documentation_status: 'not-started',
                            school_id: 'sch_1',
                            send_status: 'neither',
                        },
                    },
                    201,
                );
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
     * @param {unknown} [pupilsOverride]
     * @param {{ flush?: boolean }} [options]
     */
    async function mountPage(role = 'teacher', pupilsOverride, options = {}) {
        const { flush = true } = options;

        if (pupilsOverride !== undefined) {
            fetchMock.mockImplementation(async (url, options = {}) => {
                const path = String(url);
                const method = (options.method ?? 'GET').toUpperCase();

                if (path.includes('/api/v1/pupils') && method === 'GET') {
                    return jsonResponse({ data: pupilsOverride });
                }

                if (path.includes('/api/v1/schools')) {
                    return jsonResponse({
                        data: [{ id: 'sch_1', name: 'Northbridge Primary', is_active: true }],
                    });
                }

                if (path.includes('/api/v1/pupils') && method === 'POST') {
                    return jsonResponse(
                        {
                            data: {
                                id: 'pup_new',
                                given_name: 'Sam',
                                family_name: 'Patel',
                                year_group: 'Year 7',
                                documentation_status: 'not-started',
                            },
                        },
                        201,
                    );
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
                { path: '/pupils', name: 'pupils', component: PupilsPage, meta: { title: 'Pupils' } },
                { path: '/pupils/:id', name: 'pupil-detail', component: { template: '<div>detail</div>' } },
                { path: '/import', component: { template: '<div>import</div>' } },
            ],
        });

        await router.push('/pupils');
        await router.isReady();

        const wrapper = mount(PupilsPage, {
            global: {
                plugins: [router],
            },
        });

        if (flush) {
            await flushPromises();
        }

        return { wrapper, router };
    }

    it('shows My Pupils list with name, year, StatusPill, and next-review placeholder for Teacher', async () => {
        const { wrapper } = await mountPage('teacher');

        expect(wrapper.find('[data-testid="pupils-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('My Pupils');
        expect(document.title).toBe('My Pupils');
        expect(wrapper.find('[data-testid="pupils-list"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="pupil-row"]').length).toBe(2);
        expect(wrapper.find('[data-testid="pupil-name"]').text()).toContain('Alex Rivera');
        expect(wrapper.find('[data-testid="pupil-year"]').text()).toContain('Year 8');
        expect(wrapper.findAll('[data-testid="status-pill"]').length).toBe(2);
        expect(wrapper.find('[data-testid="pupil-next-review"]').text()).toContain('—');
        expect(wrapper.find('[data-testid="pupils-empty-ctas"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupil-edit-pup_1"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-add-open"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.text()).not.toMatch(/never colour alone/i);
    });

    it('formats next_review_at from the earliest open Review Cycle', async () => {
        const { wrapper } = await mountPage('senco', [
            {
                id: 'pup_1',
                given_name: 'Alex',
                family_name: 'Rivera',
                year_group: 'Year 8',
                documentation_status: 'not-started',
                next_review_at: '2026-10-15',
            },
        ]);

        expect(wrapper.find('[data-testid="pupil-next-review"]').text()).toContain('15 Oct 2026');
        expect(wrapper.find('[data-testid="pupil-next-review"]').text()).not.toContain('—');
    });

    it('titles SENCO page Pupils and Support Staff My Pupils (document title too)', async () => {
        const senco = await mountPage('senco');
        expect(senco.wrapper.find('h1').text()).toBe('Pupils');
        expect(document.title).toBe('Pupils');
        senco.wrapper.unmount();

        const support = await mountPage('support_staff');
        expect(support.wrapper.find('h1').text()).toBe('My Pupils');
        expect(document.title).toBe('My Pupils');
        support.wrapper.unmount();
    });

    it('shows empty copy without Add/Import CTAs for Teacher', async () => {
        const { wrapper } = await mountPage('teacher', []);

        expect(wrapper.find('[data-testid="pupils-empty"]').text()).toContain(
            'No Pupils assigned yet. Ask your SENCO to assign Pupils to you.',
        );
        expect(wrapper.find('[data-testid="pupils-empty-ctas"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-import-cta"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-add-cta"]').exists()).toBe(false);
    });

    it('shows empty copy without Add/Import CTAs for Support Staff', async () => {
        const { wrapper } = await mountPage('support_staff', []);

        expect(wrapper.find('[data-testid="pupils-empty"]').text()).toContain(
            'No Pupils assigned yet. Ask your SENCO to assign Pupils to you.',
        );
        expect(wrapper.find('[data-testid="pupils-empty-ctas"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-import-cta"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-add-cta"]').exists()).toBe(false);
    });

    it('shows SENCO empty CTAs for Import and Add Pupil, and opens Add in a dialog', async () => {
        const { wrapper, router } = await mountPage('senco', []);

        expect(wrapper.find('[data-testid="pupils-empty"]').text()).toContain('No Pupils in your list.');
        expect(wrapper.find('[data-testid="pupils-empty-ctas"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pupils-import-cta"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pupils-add-cta"]').exists()).toBe(true);

        await wrapper.find('[data-testid="pupils-import-cta"]').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.path).toBe('/import');

        await router.push('/pupils');
        await flushPromises();

        await wrapper.find('[data-testid="pupils-add-cta"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="pupils-add-form"]');
        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(wrapper.find('[data-testid="pupils-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pupil-send-status"]').html()).toContain('Neither');
        expect(wrapper.find('[data-testid="pupil-primary-need"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pupil-assignments"]').exists()).toBe(true);
    });

    it('saves Need and Teacher assignment when SENCO creates a Pupil', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="pupils-add-open"]').trigger('click');
        await flushPromises();

        await wrapper.find('[data-testid="pupil-school"]').setValue('sch_1');
        await wrapper.find('[data-testid="pupil-given-name"]').setValue('Sam');
        await wrapper.find('[data-testid="pupil-family-name"]').setValue('Patel');
        await wrapper.find('[data-testid="pupil-year-group"]').setValue('Year 7');
        await wrapper.find('[data-testid="pupil-primary-need"]').setValue('need_ci');
        expect(wrapper.find('[data-testid="pupil-assignee-11"]').exists()).toBe(true);
        await wrapper.find('[data-testid="pupil-assignee-11"]').setValue(true);
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        const postPupil = fetchMock.mock.calls.find(([url, options]) => {
            const path = String(url);
            const method = String(options?.method ?? 'GET').toUpperCase();

            return path.includes('/api/v1/pupils') && method === 'POST' && !path.includes('/assignments');
        });

        expect(postPupil).toBeTruthy();
        expect(JSON.parse(String(postPupil[1].body)).primary_need_term_id).toBe('need_ci');

        const postAssign = fetchMock.mock.calls.find((call) => {
            const path = String(call[0]);
            const method = String(call[1]?.method ?? 'GET').toUpperCase();

            return path.includes('/assignments') && method === 'POST';
        });

        expect(postAssign).toBeTruthy();
        expect(JSON.parse(String(postAssign[1].body)).user_id).toBe(11);
    });

    it('shows loading skeleton before pupils resolve', async () => {
        let resolvePupils;
        const pupilsPromise = new Promise((resolve) => {
            resolvePupils = resolve;
        });

        fetchMock.mockImplementation(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/pupils')) {
                await pupilsPromise;

                return jsonResponse({ data: samplePupils });
            }

            return jsonResponse({});
        });

        const { wrapper } = await mountPage('teacher', undefined, { flush: false });

        expect(wrapper.find('[data-testid="pupils-loading"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pupils-list"]').exists()).toBe(false);

        resolvePupils();
        await flushPromises();

        expect(wrapper.find('[data-testid="pupils-loading"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-list"]').exists()).toBe(true);
    });

    it('filters scoped Pupils by name and year group search', async () => {
        const { wrapper } = await mountPage('teacher');

        await wrapper.find('[data-testid="pupils-search"]').setValue('Jordan');
        await flushPromises();

        expect(wrapper.findAll('[data-testid="pupil-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="pupil-name"]').text()).toContain('Jordan Lee');

        await wrapper.find('[data-testid="pupils-search"]').setValue('Year 8');
        await flushPromises();

        expect(wrapper.findAll('[data-testid="pupil-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="pupil-name"]').text()).toContain('Alex Rivera');

        await wrapper.find('[data-testid="pupils-search"]').setValue('');
        await flushPromises();

        expect(wrapper.findAll('[data-testid="pupil-row"]').length).toBe(2);
    });

    it('shows evaluating StatusPill with spinner and polls until status clears', async () => {
        vi.useFakeTimers();

        let pollCount = 0;
        fetchMock.mockImplementation(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/pupils')) {
                pollCount += 1;

                if (pollCount === 1) {
                    return jsonResponse({
                        data: [
                            {
                                id: 'pup_eval',
                                given_name: 'Casey',
                                family_name: 'Ng',
                                year_group: 'Year 5',
                                documentation_status: 'evaluating',
                            },
                        ],
                    });
                }

                return jsonResponse({
                    data: [
                        {
                            id: 'pup_eval',
                            given_name: 'Casey',
                            family_name: 'Ng',
                            year_group: 'Year 5',
                            documentation_status: 'ready',
                        },
                    ],
                });
            }

            return jsonResponse({});
        });

        const { wrapper } = await mountPage('teacher');

        expect(wrapper.find('[data-testid="status-pill"]').text()).toContain('Evaluating');
        expect(wrapper.find('[data-testid="status-pill-icon"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="status-pill-spinner"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pupils-loading"]').exists()).toBe(false);

        await vi.advanceTimersByTimeAsync(2000);
        await flushPromises();

        expect(wrapper.find('[data-testid="status-pill"]').text()).toContain('Ready');
        expect(wrapper.find('[data-testid="status-pill-spinner"]').exists()).toBe(false);
        expect(pollCount).toBeGreaterThanOrEqual(2);

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('stops polling after unmount so further timers do not fetch', async () => {
        vi.useFakeTimers();

        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/pupils')) {
                return jsonResponse({
                    data: [
                        {
                            id: 'pup_eval',
                            given_name: 'Casey',
                            family_name: 'Ng',
                            year_group: 'Year 5',
                            documentation_status: 'evaluating',
                        },
                    ],
                });
            }

            return jsonResponse({});
        });

        const { wrapper } = await mountPage('teacher');
        const callsAfterMount = fetchMock.mock.calls.filter(([url]) =>
            String(url).includes('/api/v1/pupils'),
        ).length;

        expect(callsAfterMount).toBeGreaterThanOrEqual(1);

        wrapper.unmount();

        await vi.advanceTimersByTimeAsync(10000);
        await flushPromises();

        const callsAfterUnmount = fetchMock.mock.calls.filter(([url]) =>
            String(url).includes('/api/v1/pupils'),
        ).length;

        expect(callsAfterUnmount).toBe(callsAfterMount);
        vi.useRealTimers();
    });

    it('does not wipe pupils list when poll payload data is missing', async () => {
        vi.useFakeTimers();

        let pollCount = 0;
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/pupils')) {
                pollCount += 1;

                if (pollCount === 1) {
                    return jsonResponse({
                        data: [
                            {
                                id: 'pup_eval',
                                given_name: 'Casey',
                                family_name: 'Ng',
                                year_group: 'Year 5',
                                documentation_status: 'evaluating',
                            },
                        ],
                    });
                }

                return jsonResponse({ message: 'malformed' });
            }

            return jsonResponse({});
        });

        const { wrapper } = await mountPage('teacher');

        expect(wrapper.find('[data-testid="pupil-name"]').text()).toContain('Casey Ng');

        await vi.advanceTimersByTimeAsync(2000);
        await flushPromises();

        expect(wrapper.find('[data-testid="pupils-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pupil-name"]').text()).toContain('Casey Ng');
        expect(wrapper.find('[data-testid="status-pill"]').text()).toContain('Evaluating');

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('links each pupil row to /pupils/:id', async () => {
        const { wrapper } = await mountPage('teacher');

        const link = wrapper.find('[data-testid="pupil-row-link-pup_1"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toBe('/pupils/pup_1');
    });

    it('shows 422 inline field errors on Add Pupil', async () => {
        const { wrapper } = await mountPage('senco', []);

        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({
                    data: [{ id: 'sch_1', name: 'Northbridge Primary', is_active: true }],
                });
            }

            if (path.includes('/api/v1/pupils') && method === 'POST') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: {
                            given_name: ['The given name field is required.'],
                            year_group: ['The year group field is required.'],
                        },
                    },
                    422,
                );
            }

            return jsonResponse({ data: [] });
        });

        await wrapper.find('[data-testid="pupils-add-cta"]').trigger('click');
        await flushPromises();

        await wrapper.find('[data-testid="pupil-given-name"]').setValue(' ');
        await wrapper.find('[data-testid="pupil-family-name"]').setValue('Patel');
        await wrapper.find('[data-testid="pupil-year-group"]').setValue(' ');
        await wrapper.find('[data-testid="pupil-school"]').setValue('sch_1');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-given_name"]').text()).toContain('given name');
        expect(wrapper.find('[data-testid="error-year_group"]').text()).toContain('year group');
        expect(wrapper.find('[data-testid="pupils-add-form"]').exists()).toBe(true);
    });

    it('creates a Pupil via SENCO Add form with trimmed POST body and clears search', async () => {
        const { wrapper } = await mountPage('senco', []);

        await wrapper.find('[data-testid="pupils-add-cta"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="pupils-add-form"]').exists()).toBe(true);

        await wrapper.find('[data-testid="pupils-search"]').setValue('noise');
        await wrapper.find('[data-testid="pupil-given-name"]').setValue('  Sam  ');
        await wrapper.find('[data-testid="pupil-family-name"]').setValue('  Patel  ');
        await wrapper.find('[data-testid="pupil-year-group"]').setValue('  Year 7  ');
        await wrapper.find('[data-testid="pupil-school"]').setValue('sch_1');
        await wrapper.find('[data-testid="pupil-send-status"]').setValue('neither');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url).includes('/api/v1/pupils') && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            school_id: 'sch_1',
            given_name: 'Sam',
            family_name: 'Patel',
            year_group: 'Year 7',
            send_status: 'neither',
            mis_key: null,
            date_of_birth: null,
            notes: null,
            primary_need_term_id: null,
            primary_need_notes: null,
            secondary_need_term_id: null,
            secondary_need_notes: null,
        });
        expect(wrapper.find('[data-testid="pupils-search"]').element.value).toBe('');
        expect(wrapper.find('[data-testid="pupils-list"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Sam Patel');
        expect(wrapper.find('[data-testid="pupils-add-form"]').exists()).toBe(false);
    });

    it('updates a Pupil via PATCH from the Edit dialog', async () => {
        const { wrapper } = await mountPage('senco', [
            {
                id: 'pup_1',
                given_name: 'Alex',
                family_name: 'Rivera',
                year_group: 'Year 8',
                documentation_status: 'not-started',
                school_id: 'sch_1',
                send_status: 'neither',
            },
        ]);

        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({
                    data: [{ id: 'sch_1', name: 'Northbridge Primary', is_active: true }],
                });
            }

            if (path.includes('/api/v1/pupils/pup_1') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        id: 'pup_1',
                        given_name: 'Alexandra',
                        family_name: 'Rivera',
                        year_group: 'Year 9',
                        documentation_status: 'not-started',
                        school_id: 'sch_1',
                        send_status: 'sen_support',
                    },
                });
            }

            return jsonResponse({ data: [] });
        });

        await wrapper.find('[data-testid="pupil-edit-pup_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="pupils-add-form"]').text()).toContain('Edit Pupil');
        expect(wrapper.find('[data-testid="pupils-list"]').exists()).toBe(true);

        await wrapper.find('[data-testid="pupil-given-name"]').setValue('Alexandra');
        await wrapper.find('[data-testid="pupil-year-group"]').setValue('Year 9');
        await wrapper.find('[data-testid="pupil-send-status"]').setValue('sen_support');
        await wrapper.find('[data-testid="pupils-add-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url).includes('/api/v1/pupils/pup_1') && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toMatchObject({
            given_name: 'Alexandra',
            year_group: 'Year 9',
            send_status: 'sen_support',
        });
        expect(wrapper.text()).toContain('Alexandra Rivera');
        expect(wrapper.find('[data-testid="pupils-add-form"]').exists()).toBe(false);
    });

    it('deletes a Pupil via DELETE after confirm', async () => {
        const { wrapper } = await mountPage('tenant_admin', [
            {
                id: 'pup_1',
                given_name: 'Alex',
                family_name: 'Rivera',
                year_group: 'Year 8',
                documentation_status: 'not-started',
                school_id: 'sch_1',
            },
        ]);

        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/pupils/pup_1') && method === 'DELETE') {
                return {
                    ok: true,
                    status: 204,
                    clone() {
                        return this;
                    },
                    async json() {
                        return {};
                    },
                };
            }

            return jsonResponse({ data: [] });
        });

        await wrapper.find('[data-testid="pupil-delete-pup_1"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="pupils-delete-confirm"]').text()).toContain('Alex Rivera');

        await wrapper.find('[data-testid="pupils-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        const deleteCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url).includes('/api/v1/pupils/pup_1') && (options?.method ?? 'GET').toUpperCase() === 'DELETE',
        );

        expect(deleteCall).toBeTruthy();
        expect(wrapper.text()).not.toContain('Alex Rivera');
        expect(wrapper.find('[data-testid="pupils-list"]').exists()).toBe(false);
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
