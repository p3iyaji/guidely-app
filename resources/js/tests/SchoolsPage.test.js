/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import SchoolsPage from '../pages/SchoolsPage.vue';

const sampleSchools = [
    {
        id: 'sch_1',
        name: 'Northbridge Primary',
        is_active: true,
        address: '12 High Street',
        city: 'Leeds',
        county: 'West Yorkshire',
        postcode: 'LS1 2AB',
        country: 'United Kingdom',
    },
    {
        id: 'sch_2',
        name: 'Oak Academy',
        is_active: false,
        address: null,
        city: null,
        county: null,
        postcode: null,
        country: null,
    },
];

describe('SchoolsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/schools') && method === 'GET') {
                return jsonResponse({ data: sampleSchools });
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

        const wrapper = mount(SchoolsPage);

        if (flush) {
            await flushPromises();
        }

        return wrapper;
    }

    it('lists Schools with Active and Inactive status for Tenant Admin', async () => {
        const wrapper = await mountPage('tenant_admin');

        expect(wrapper.find('[data-testid="schools-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Schools');
        expect(wrapper.findAll('[data-testid="school-row"]').length).toBe(2);
        expect(wrapper.text()).toContain('Northbridge Primary');
        expect(wrapper.text()).toContain('Oak Academy');
        expect(wrapper.findAll('[data-testid="school-status"]').map((node) => node.text())).toEqual([
            'Active',
            'Inactive',
        ]);
        expect(wrapper.find('[data-testid="school-location"]').text()).toContain('Leeds');
        expect(wrapper.find('[data-testid="school-location"]').text()).toContain('LS1 2AB');
        expect(wrapper.find('[data-testid="schools-add-open"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="schools-form"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
    });

    it('opens Add School in a dialog and keeps the empty state behind it', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/schools')) {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="schools-add-cta"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="schools-form"]');

        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(wrapper.find('[data-testid="schools-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="school-country-input"]').element.value).toBe('United Kingdom');
    });

    it('keeps the Schools list visible while the Edit dialog is open and closes on backdrop click', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="school-edit-sch_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="schools-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="schools-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="schools-form"]').text()).toContain('Edit School');
        expect(wrapper.find('[data-testid="school-city-input"]').element.value).toBe('Leeds');

        await wrapper.find('[data-testid="modal-backdrop"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="schools-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="schools-list"]').exists()).toBe(true);
    });

    it('hides create, edit, and delete for Trust SEND Lead', async () => {
        const wrapper = await mountPage('trust_send_lead');

        expect(wrapper.find('[data-testid="schools-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="schools-add-open"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="school-edit-sch_1"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="school-delete-sch_1"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('limited to Tenant Admins');
    });

    it('filters Schools by name, city, and postcode', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="schools-search"]').setValue('Oak');
        await flushPromises();

        expect(wrapper.findAll('[data-testid="school-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="school-name"]').text()).toContain('Oak Academy');

        await wrapper.find('[data-testid="schools-search"]').setValue('LS1');
        await flushPromises();

        expect(wrapper.findAll('[data-testid="school-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="school-name"]').text()).toContain('Northbridge Primary');
    });

    it('shows empty copy and Add School CTA for Tenant Admin', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/schools')) {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage('tenant_admin');

        expect(wrapper.find('[data-testid="schools-empty"]').text()).toContain('No Schools in this Tenant.');
        expect(wrapper.find('[data-testid="schools-add-cta"]').exists()).toBe(true);
    });

    it('hides Add School CTA on empty list for Trust Executive', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/schools')) {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage('trust_executive');

        expect(wrapper.find('[data-testid="schools-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="schools-add-cta"]').exists()).toBe(false);
    });

    it('creates a School via POST with trimmed name and address fields', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/schools') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/schools') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'sch_new',
                            name: 'River Primary',
                            is_active: true,
                            address: '1 River Lane',
                            city: 'York',
                            county: 'North Yorkshire',
                            postcode: 'YO1 7HH',
                            country: 'United Kingdom',
                        },
                    },
                    201,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="schools-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="school-name-input"]').setValue('  River Primary  ');
        await wrapper.find('[data-testid="school-address-input"]').setValue('  1 River Lane  ');
        await wrapper.find('[data-testid="school-city-input"]').setValue('  York  ');
        await wrapper.find('[data-testid="school-county-input"]').setValue('  North Yorkshire  ');
        await wrapper.find('[data-testid="school-postcode-input"]').setValue('  YO1 7HH  ');
        await wrapper.find('[data-testid="schools-form"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/schools' && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            name: 'River Primary',
            is_active: true,
            address: '1 River Lane',
            postcode: 'YO1 7HH',
            city: 'York',
            county: 'North Yorkshire',
            country: 'United Kingdom',
        });
        expect(wrapper.text()).toContain('River Primary');
        expect(wrapper.text()).toContain('York');
    });

    it('shows 422 inline name error on Add School', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/schools') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (method === 'POST') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: { name: ['The name field is required.'] },
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="schools-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="school-name-input"]').setValue(' ');
        await wrapper.find('[data-testid="schools-form"] form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-name"]').text()).toContain('name');
        expect(wrapper.find('[data-testid="schools-form"]').exists()).toBe(true);
    });

    it('updates a School via PATCH including inactive flag', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/schools') && method === 'GET') {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/schools/sch_1') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        id: 'sch_1',
                        name: 'Northbridge Updated',
                        is_active: false,
                        address: '12 High Street',
                        city: 'Leeds',
                        county: 'West Yorkshire',
                        postcode: 'LS1 2AB',
                        country: 'United Kingdom',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="school-edit-sch_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="school-name-input"]').setValue('Northbridge Updated');
        await wrapper.find('[data-testid="school-active-input"]').setValue(false);
        await wrapper.find('[data-testid="schools-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/schools/sch_1' && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            name: 'Northbridge Updated',
            is_active: false,
            address: '12 High Street',
            postcode: 'LS1 2AB',
            city: 'Leeds',
            county: 'West Yorkshire',
            country: 'United Kingdom',
        });
        expect(wrapper.text()).toContain('Northbridge Updated');
    });

    it('deletes a School via DELETE after confirm', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/schools') && method === 'GET') {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/schools/sch_2') && method === 'DELETE') {
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

        await wrapper.find('[data-testid="school-delete-sch_2"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="schools-delete-confirm"]').text()).toContain('Oak Academy');

        await wrapper.find('[data-testid="schools-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        const deleteCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/schools/sch_2' && (options?.method ?? 'GET').toUpperCase() === 'DELETE',
        );

        expect(deleteCall).toBeTruthy();
        expect(wrapper.text()).not.toContain('Oak Academy');
        expect(wrapper.findAll('[data-testid="school-row"]').length).toBe(1);
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
