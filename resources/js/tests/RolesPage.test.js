/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import RolesPage from '../pages/RolesPage.vue';

const samplePermissions = [
    {
        id: 'perm_1',
        key: 'manage_pupils',
        label: 'Manage Pupils',
        is_system: true,
        group: 'Pupils',
    },
];

const sampleRoles = [
    {
        id: 'role_teacher',
        key: 'teacher',
        label: 'Teacher',
        is_system: true,
        permission_ids: ['perm_1'],
        permissions: [{ id: 'perm_1', label: 'Manage Pupils' }],
    },
    {
        id: 'role_custom',
        key: 'year_lead',
        label: 'Year Lead',
        is_system: false,
        permission_ids: [],
        permissions: [],
    },
];

describe('RolesPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/roles')) {
                return jsonResponse({ data: sampleRoles });
            }

            if (path.includes('/api/v1/permissions')) {
                return jsonResponse({ data: samplePermissions });
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
     */
    async function mountPage(role = 'tenant_admin') {
        useSession().setUser({
            id: 'usr_1',
            name: 'Test User',
            email: 'test@example.com',
            role,
            tenant_id: 'ten_1',
        });

        const wrapper = mount(RolesPage);

        await flushPromises();

        return wrapper;
    }

    it('lists built-in and custom Roles for Tenant Admin', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="roles-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Roles');
        expect(wrapper.findAll('[data-testid="role-row"]').length).toBe(2);
        expect(wrapper.text()).toContain('Teacher');
        expect(wrapper.text()).toContain('Year Lead');
        expect(wrapper.find('[data-testid="role-edit-role_teacher"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="role-edit-role_custom"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="role-delete-role_teacher"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="role-delete-role_custom"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="roles-add-open"]').exists()).toBe(true);
    });

    it('shows access error for Teacher without fetching', async () => {
        const wrapper = await mountPage('teacher');

        expect(wrapper.find('[data-testid="roles-error"]').text()).toContain('You don’t have access.');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('creates a custom Role via POST', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/permissions')) {
                return jsonResponse({ data: samplePermissions });
            }

            if (path.includes('/api/v1/roles') && method === 'GET') {
                return jsonResponse({ data: sampleRoles });
            }

            if (path.includes('/api/v1/roles') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'role_new',
                            key: 'phase_lead',
                            label: 'Phase Lead',
                            is_system: false,
                            permission_ids: ['perm_1'],
                            permissions: [{ id: 'perm_1', label: 'Manage Pupils' }],
                        },
                    },
                    201,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="roles-add-open"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="role-key-input"]').setValue('phase_lead');
        await wrapper.find('[data-testid="role-label-input"]').setValue('Phase Lead');
        await wrapper.find('[data-testid="role-permission-perm_1"]').setValue(true);
        await wrapper.find('[data-testid="roles-form"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/roles' && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            key: 'phase_lead',
            label: 'Phase Lead',
            description: null,
            permission_ids: ['perm_1'],
        });
        expect(wrapper.text()).toContain('Phase Lead');
    });

    it('edits a built-in Role via PATCH and locks the key', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/permissions')) {
                return jsonResponse({ data: samplePermissions });
            }

            if (path.includes('/api/v1/roles') && method === 'GET') {
                return jsonResponse({ data: sampleRoles });
            }

            if (path.includes('/api/v1/roles/role_teacher') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        id: 'role_teacher',
                        key: 'teacher',
                        label: 'Class Teacher',
                        is_system: true,
                        permission_ids: ['perm_1'],
                        permissions: [{ id: 'perm_1', label: 'Manage Pupils' }],
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="role-edit-role_teacher"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="role-key-input"]').element.disabled).toBe(true);

        await wrapper.find('[data-testid="role-label-input"]').setValue('Class Teacher');
        await wrapper.find('[data-testid="roles-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/roles/role_teacher' && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toMatchObject({
            key: 'teacher',
            label: 'Class Teacher',
        });
        expect(wrapper.text()).toContain('Class Teacher');
    });

    it('deletes a custom Role', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/permissions')) {
                return jsonResponse({ data: samplePermissions });
            }

            if (path.includes('/api/v1/roles') && method === 'GET') {
                return jsonResponse({ data: sampleRoles });
            }

            if (path.includes('/api/v1/roles/role_custom') && method === 'DELETE') {
                return { ok: true, status: 204, async json() { return {}; }, clone() { return this; } };
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="role-delete-role_custom"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="roles-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Year Lead');
    });

    it('deletes a built-in Role', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/permissions')) {
                return jsonResponse({ data: samplePermissions });
            }

            if (path.includes('/api/v1/roles') && method === 'GET') {
                return jsonResponse({ data: sampleRoles });
            }

            if (path.includes('/api/v1/roles/role_teacher') && method === 'DELETE') {
                return { ok: true, status: 204, async json() { return {}; }, clone() { return this; } };
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="role-delete-role_teacher"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="roles-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Teacher');
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
