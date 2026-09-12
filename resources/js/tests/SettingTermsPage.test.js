/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import SettingTermsPage from '../pages/SettingTermsPage.vue';

const sampleTerms = [
    {
        id: 'st_1',
        code: 'CLASSROOM',
        label: 'Classroom',
        is_active: true,
        sort_order: 1,
        ontology_version_id: 'ont_1',
    },
    {
        id: 'st_2',
        code: 'PLAYGROUND',
        label: 'Playground & outdoor areas',
        is_active: false,
        sort_order: 6,
        ontology_version_id: 'ont_1',
    },
];

describe('SettingTermsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/setting-terms') && method === 'GET') {
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

        const wrapper = mount(SettingTermsPage);

        if (flush) {
            await flushPromises();
        }

        return wrapper;
    }

    it('lists Setting terms with Active and Inactive status for Tenant Admin', async () => {
        const wrapper = await mountPage('tenant_admin');

        expect(wrapper.find('[data-testid="setting-terms-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Setting terms');
        expect(wrapper.findAll('[data-testid="setting-term-row"]').length).toBe(2);
        expect(wrapper.text()).toContain('Classroom');
        expect(wrapper.text()).toContain('Playground & outdoor areas');
        expect(wrapper.findAll('[data-testid="setting-term-status"]').map((node) => node.text())).toEqual([
            'Active',
            'Inactive',
        ]);
        expect(wrapper.find('[data-testid="setting-terms-add-open"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="setting-terms-form"]').exists()).toBe(false);
    });

    it('opens Add Setting term in a dialog and keeps the empty state behind it', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/ontology/setting-terms')) {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="setting-terms-add-cta"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="setting-terms-form"]');

        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(wrapper.find('[data-testid="setting-terms-empty"]').exists()).toBe(true);
    });

    it('opens Edit Setting term in a dialog from the list', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="setting-term-edit-st_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="setting-terms-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="setting-terms-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="setting-terms-form"]').text()).toContain('Edit Setting term');
        expect(wrapper.find('[data-testid="setting-term-code-input"]').element.value).toBe('CLASSROOM');
        expect(wrapper.find('[data-testid="setting-term-label-input"]').element.value).toBe(
            'Classroom',
        );
    });

    it('hides add and edit actions for Teachers', async () => {
        const wrapper = await mountPage('teacher');

        expect(wrapper.find('[data-testid="setting-terms-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="setting-terms-add-open"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="setting-term-edit-st_1"]').exists()).toBe(false);
    });

    it('filters the list by code and label', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="setting-terms-search"]').setValue('PLAYGROUND');
        expect(wrapper.findAll('[data-testid="setting-term-row"]').length).toBe(1);
        expect(wrapper.text()).toContain('Playground & outdoor areas');

        await wrapper.find('[data-testid="setting-terms-search"]').setValue('CLASSROOM');
        expect(wrapper.findAll('[data-testid="setting-term-row"]').length).toBe(1);
        expect(wrapper.text()).toContain('Classroom');
    });

    it('creates a Setting term via POST with trimmed fields', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/setting-terms') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/ontology/setting-terms') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'st_new',
                            code: 'CORRIDOR',
                            label: 'Corridor',
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

        await wrapper.find('[data-testid="setting-terms-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="setting-term-code-input"]').setValue('  corridor  ');
        await wrapper.find('[data-testid="setting-term-label-input"]').setValue('  Corridor  ');
        await wrapper.find('[data-testid="setting-term-sort-order-input"]').setValue('7');
        await wrapper.find('[data-testid="setting-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/setting-terms'
                && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            code: 'corridor',
            label: 'Corridor',
            sort_order: 7,
            is_active: true,
        });
        expect(wrapper.text()).toContain('Corridor');
        expect(wrapper.find('[data-testid="setting-terms-form"]').exists()).toBe(false);
    });

    it('shows 422 inline code error on Add Setting term', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/setting-terms') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (method === 'POST') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: { code: ['A Setting term with this code already exists on this Ontology version.'] },
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="setting-terms-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="setting-term-code-input"]').setValue('CLASSROOM');
        await wrapper.find('[data-testid="setting-term-label-input"]').setValue('Classroom');
        await wrapper.find('[data-testid="setting-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-code"]').text()).toContain('already exists');
        expect(wrapper.find('[data-testid="setting-terms-form"]').exists()).toBe(true);
    });

    it('updates a Setting term via PATCH including inactive flag', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/setting-terms') && method === 'GET') {
                return jsonResponse({ data: sampleTerms });
            }

            if (path.includes('/api/v1/ontology/setting-terms/st_1') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        id: 'st_1',
                        code: 'CLASSROOM',
                        label: 'Classroom updated',
                        is_active: false,
                        sort_order: 1,
                        ontology_version_id: 'ont_1',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="setting-term-edit-st_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="setting-term-label-input"]').setValue('Classroom updated');
        await wrapper.find('[data-testid="setting-term-active-input"]').setValue(false);
        await wrapper.find('[data-testid="setting-terms-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/setting-terms/st_1'
                && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            code: 'CLASSROOM',
            label: 'Classroom updated',
            sort_order: 1,
            is_active: false,
        });
        expect(wrapper.text()).toContain('Classroom updated');
    });

    it('deletes a Setting term via DELETE after confirm', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/setting-terms') && method === 'GET') {
                return jsonResponse({ data: sampleTerms });
            }

            if (path.includes('/api/v1/ontology/setting-terms/st_2') && method === 'DELETE') {
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

        await wrapper.find('[data-testid="setting-term-delete-st_2"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="setting-terms-delete-confirm"]').text()).toContain(
            'Playground & outdoor areas',
        );

        await wrapper.find('[data-testid="setting-terms-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        const deleteCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/setting-terms/st_2'
                && (options?.method ?? 'GET').toUpperCase() === 'DELETE',
        );

        expect(deleteCall).toBeTruthy();
        expect(wrapper.text()).not.toContain('Playground & outdoor areas');
        expect(wrapper.findAll('[data-testid="setting-term-row"]').length).toBe(1);
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
