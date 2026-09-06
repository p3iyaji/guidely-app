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
        const gapsIcon = gaps.find('[aria-hidden="true"]');
        expect(gapsIcon.exists()).toBe(true);
        expect(gapsIcon.text().trim().length).toBeGreaterThan(0);

        const evaluating = mount(StatusPill, { props: { status: 'evaluating' } });
        expect(evaluating.text()).toContain('Evaluating');
        const evaluatingIcon = evaluating.find('[aria-hidden="true"]');
        expect(evaluatingIcon.exists()).toBe(true);
        expect(evaluatingIcon.text().trim().length).toBeGreaterThan(0);
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
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.text()).not.toMatch(/never colour alone/i);
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

        expect(wrapper.find('[data-testid="pupils-empty"]').text()).toContain('No Pupils in your list.');
        expect(wrapper.find('[data-testid="pupils-empty-ctas"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-import-cta"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-add-cta"]').exists()).toBe(false);
    });

    it('shows empty copy without Add/Import CTAs for Support Staff', async () => {
        const { wrapper } = await mountPage('support_staff', []);

        expect(wrapper.find('[data-testid="pupils-empty"]').text()).toContain('No Pupils in your list.');
        expect(wrapper.find('[data-testid="pupils-empty-ctas"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-import-cta"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-add-cta"]').exists()).toBe(false);
    });

    it('shows SENCO empty CTAs for Import and Add Pupil, and hides them when form opens', async () => {
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

        expect(wrapper.find('[data-testid="pupils-add-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pupils-empty"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupils-empty-ctas"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pupil-send-status"]').html()).toContain('Neither');
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
        });
        expect(wrapper.find('[data-testid="pupils-search"]').element.value).toBe('');
        expect(wrapper.find('[data-testid="pupils-list"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Sam Patel');
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
