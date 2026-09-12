/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import OutcomeTermsPage from '../pages/OutcomeTermsPage.vue';

const sampleTerms = [
    {
        id: 'ot_1',
        code: 'INDEPENDENT',
        label: 'Independent',
        is_active: true,
        sort_order: 1,
        ontology_version_id: 'ont_1',
    },
    {
        id: 'ot_2',
        code: 'CONFIDENT',
        label: 'Confident & self-assured',
        is_active: false,
        sort_order: 6,
        ontology_version_id: 'ont_1',
    },
];

describe('OutcomeTermsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/outcome-terms') && method === 'GET') {
                return jsonResponse({ data: sampleTerms });
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
     * @param {{ flush?: boolean }} [options]
     */
    async function mountPage(role = 'tenant_admin', options = {}) {
        const { flush = true } = options;

        useSession().setUser({
            id: 'usr_1',
            name: 'Test User',
            email: 'test@example.com',
            role,
            tenant_id: 'ten_1',
        });

        const wrapper = mount(OutcomeTermsPage);

        if (flush) {
            await flushPromises();
        }

        return wrapper;
    }

    it('lists Outcome terms with Active and Inactive status for Tenant Admin', async () => {
        const wrapper = await mountPage('tenant_admin');

        expect(wrapper.find('[data-testid="outcome-terms-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Outcome terms');
        expect(wrapper.findAll('[data-testid="outcome-term-row"]').length).toBe(2);
        expect(wrapper.text()).toContain('Independent');
        expect(wrapper.text()).toContain('Confident & self-assured');
        expect(wrapper.findAll('[data-testid="outcome-term-status"]').map((node) => node.text())).toEqual([
            'Active',
            'Inactive',
        ]);
        expect(wrapper.find('[data-testid="outcome-terms-add-open"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="outcome-terms-form"]').exists()).toBe(false);
    });

    it('opens Add Outcome term in a dialog and keeps the empty state behind it', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/ontology/outcome-terms')) {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="outcome-terms-add-cta"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="outcome-terms-form"]');

        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(wrapper.find('[data-testid="outcome-terms-empty"]').exists()).toBe(true);
    });

    it('opens Edit Outcome term in a dialog from the list', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="outcome-term-edit-ot_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="outcome-terms-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="outcome-terms-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="outcome-terms-form"]').text()).toContain('Edit Outcome term');
        expect(wrapper.find('[data-testid="outcome-term-code-input"]').element.value).toBe('INDEPENDENT');
        expect(wrapper.find('[data-testid="outcome-term-label-input"]').element.value).toBe(
            'Independent',
        );
    });

    it('hides add and edit actions for Teachers', async () => {
        const wrapper = await mountPage('teacher');

        expect(wrapper.find('[data-testid="outcome-terms-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="outcome-terms-add-open"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="outcome-term-edit-ot_1"]').exists()).toBe(false);
    });

    it('filters the list by code and label', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="outcome-terms-search"]').setValue('CONFIDENT');
        expect(wrapper.findAll('[data-testid="outcome-term-row"]').length).toBe(1);
        expect(wrapper.text()).toContain('Confident & self-assured');

        await wrapper.find('[data-testid="outcome-terms-search"]').setValue('INDEPENDENT');
        expect(wrapper.findAll('[data-testid="outcome-term-row"]').length).toBe(1);
        expect(wrapper.text()).toContain('Independent');
    });

    it('creates an Outcome term via POST with trimmed fields', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/outcome-terms') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/ontology/outcome-terms') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'ot_new',
                            code: 'RESILIENT',
                            label: 'Resilient',
                            is_active: true,
                            sort_order: 7,
                            ontology_version_id: 'ont_1',
                        },
                    },
                    201,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="outcome-terms-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="outcome-term-code-input"]').setValue('  resilient  ');
        await wrapper.find('[data-testid="outcome-term-label-input"]').setValue('  Resilient  ');
        await wrapper.find('[data-testid="outcome-term-sort-order-input"]').setValue('7');
        await wrapper.find('[data-testid="outcome-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/outcome-terms'
                && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            code: 'resilient',
            label: 'Resilient',
            sort_order: 7,
            is_active: true,
        });
        expect(wrapper.text()).toContain('Resilient');
        expect(wrapper.find('[data-testid="outcome-terms-form"]').exists()).toBe(false);
    });

    it('shows 422 inline code error on Add Outcome term', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/outcome-terms') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (method === 'POST') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: { code: ['An Outcome term with this code already exists on this Ontology version.'] },
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="outcome-terms-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="outcome-term-code-input"]').setValue('INDEPENDENT');
        await wrapper.find('[data-testid="outcome-term-label-input"]').setValue('Independent');
        await wrapper.find('[data-testid="outcome-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-code"]').text()).toContain('already exists');
        expect(wrapper.find('[data-testid="outcome-terms-form"]').exists()).toBe(true);
    });

    it('updates an Outcome term via PATCH including inactive flag', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/outcome-terms') && method === 'GET') {
                return jsonResponse({ data: sampleTerms });
            }

            if (path.includes('/api/v1/ontology/outcome-terms/ot_1') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        id: 'ot_1',
                        code: 'INDEPENDENT',
                        label: 'Independent updated',
                        is_active: false,
                        sort_order: 1,
                        ontology_version_id: 'ont_1',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="outcome-term-edit-ot_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="outcome-term-label-input"]').setValue('Independent updated');
        await wrapper.find('[data-testid="outcome-term-active-input"]').setValue(false);
        await wrapper.find('[data-testid="outcome-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/outcome-terms/ot_1'
                && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            code: 'INDEPENDENT',
            label: 'Independent updated',
            sort_order: 1,
            is_active: false,
        });
        expect(wrapper.text()).toContain('Independent updated');
    });

    it('deletes an Outcome term via DELETE after confirm', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/outcome-terms') && method === 'GET') {
                return jsonResponse({ data: sampleTerms });
            }

            if (path.includes('/api/v1/ontology/outcome-terms/ot_2') && method === 'DELETE') {
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

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="outcome-term-delete-ot_2"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="outcome-terms-delete-confirm"]').text()).toContain(
            'Confident & self-assured',
        );

        await wrapper.find('[data-testid="outcome-terms-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        const deleteCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/outcome-terms/ot_2'
                && (options?.method ?? 'GET').toUpperCase() === 'DELETE',
        );

        expect(deleteCall).toBeTruthy();
        expect(wrapper.text()).not.toContain('Confident & self-assured');
        expect(wrapper.findAll('[data-testid="outcome-term-row"]').length).toBe(1);
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
