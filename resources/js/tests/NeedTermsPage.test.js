/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import NeedTermsPage from '../pages/NeedTermsPage.vue';

const sampleTerms = [
    {
        id: 'ndt_1',
        code: 'LEARNING',
        label: 'Learning needs',
        is_active: true,
        sort_order: 1,
        ontology_version_id: 'ont_1',
    },
    {
        id: 'ndt_2',
        code: 'SOCIAL_EMOTIONAL',
        label: 'Social & emotional support',
        is_active: false,
        sort_order: 6,
        ontology_version_id: 'ont_1',
    },
];

describe('NeedTermsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/need-terms') && method === 'GET') {
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

        const wrapper = mount(NeedTermsPage);

        if (flush) {
            await flushPromises();
        }

        return wrapper;
    }

    it('lists Need terms with Active and Inactive status for Tenant Admin', async () => {
        const wrapper = await mountPage('tenant_admin');

        expect(wrapper.find('[data-testid="need-terms-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Need terms');
        expect(wrapper.findAll('[data-testid="need-term-row"]').length).toBe(2);
        expect(wrapper.text()).toContain('Learning needs');
        expect(wrapper.text()).toContain('Social & emotional support');
        expect(wrapper.findAll('[data-testid="need-term-status"]').map((node) => node.text())).toEqual([
            'Active',
            'Inactive',
        ]);
        expect(wrapper.find('[data-testid="need-terms-add-open"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="need-terms-form"]').exists()).toBe(false);
    });

    it('opens Add Need term in a dialog and keeps the empty state behind it', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/ontology/need-terms')) {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="need-terms-add-cta"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="need-terms-form"]');

        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(wrapper.find('[data-testid="need-terms-empty"]').exists()).toBe(true);
    });

    it('opens Edit Need term in a dialog from the list', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="need-term-edit-ndt_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="need-terms-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="need-terms-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="need-terms-form"]').text()).toContain('Edit Need term');
        expect(wrapper.find('[data-testid="need-term-code-input"]').element.value).toBe('LEARNING');
        expect(wrapper.find('[data-testid="need-term-label-input"]').element.value).toBe(
            'Learning needs',
        );
    });

    it('hides add and edit actions for Teachers', async () => {
        const wrapper = await mountPage('teacher');

        expect(wrapper.find('[data-testid="need-terms-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="need-terms-add-open"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="need-term-edit-ndt_1"]').exists()).toBe(false);
    });

    it('filters the list by code and label', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="need-terms-search"]').setValue('SOCIAL');
        expect(wrapper.findAll('[data-testid="need-term-row"]').length).toBe(1);
        expect(wrapper.text()).toContain('Social & emotional support');

        await wrapper.find('[data-testid="need-terms-search"]').setValue('LEARNING');
        expect(wrapper.findAll('[data-testid="need-term-row"]').length).toBe(1);
        expect(wrapper.text()).toContain('Learning needs');
    });

    it('creates a Need term via POST with trimmed fields', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/need-terms') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/ontology/need-terms') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'ndt_new',
                            code: 'MENTORING',
                            label: 'Peer mentoring',
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

        await wrapper.find('[data-testid="need-terms-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="need-term-code-input"]').setValue('  mentoring  ');
        await wrapper.find('[data-testid="need-term-label-input"]').setValue('  Peer mentoring  ');
        await wrapper.find('[data-testid="need-term-sort-order-input"]').setValue('7');
        await wrapper.find('[data-testid="need-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/need-terms'
                && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            code: 'mentoring',
            label: 'Peer mentoring',
            sort_order: 7,
            is_active: true,
        });
        expect(wrapper.text()).toContain('Peer mentoring');
        expect(wrapper.find('[data-testid="need-terms-form"]').exists()).toBe(false);
    });

    it('shows 422 inline code error on Add Need term', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/need-terms') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (method === 'POST') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: { code: ['A Need term with this code already exists on this Ontology version.'] },
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="need-terms-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="need-term-code-input"]').setValue('LEARNING');
        await wrapper.find('[data-testid="need-term-label-input"]').setValue('Learning needs');
        await wrapper.find('[data-testid="need-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-code"]').text()).toContain('already exists');
        expect(wrapper.find('[data-testid="need-terms-form"]').exists()).toBe(true);
    });

    it('updates a Need term via PATCH including inactive flag', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/need-terms') && method === 'GET') {
                return jsonResponse({ data: sampleTerms });
            }

            if (path.includes('/api/v1/ontology/need-terms/ndt_1') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        id: 'ndt_1',
                        code: 'LEARNING',
                        label: 'Learning needs updated',
                        is_active: false,
                        sort_order: 1,
                        ontology_version_id: 'ont_1',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="need-term-edit-ndt_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="need-term-label-input"]').setValue('Learning needs updated');
        await wrapper.find('[data-testid="need-term-active-input"]').setValue(false);
        await wrapper.find('[data-testid="need-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/need-terms/ndt_1'
                && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            code: 'LEARNING',
            label: 'Learning needs updated',
            sort_order: 1,
            is_active: false,
        });
        expect(wrapper.text()).toContain('Learning needs updated');
    });

    it('deletes a Need term via DELETE after confirm', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/need-terms') && method === 'GET') {
                return jsonResponse({ data: sampleTerms });
            }

            if (path.includes('/api/v1/ontology/need-terms/ndt_2') && method === 'DELETE') {
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

        await wrapper.find('[data-testid="need-term-delete-ndt_2"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="need-terms-delete-confirm"]').text()).toContain(
            'Social & emotional support',
        );

        await wrapper.find('[data-testid="need-terms-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        const deleteCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/need-terms/ndt_2'
                && (options?.method ?? 'GET').toUpperCase() === 'DELETE',
        );

        expect(deleteCall).toBeTruthy();
        expect(wrapper.text()).not.toContain('Social & emotional support');
        expect(wrapper.findAll('[data-testid="need-term-row"]').length).toBe(1);
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
