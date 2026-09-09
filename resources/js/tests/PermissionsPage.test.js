/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import PermissionsPage from '../pages/PermissionsPage.vue';

const samplePermissions = [
    {
        id: 'perm_1',
        key: 'manage_users',
        label: 'Manage Users',
        is_system: true,
        group: 'Administration',
    },
    {
        id: 'perm_2',
        key: 'approve_referrals',
        label: 'Approve referrals',
        is_system: false,
        group: 'Custom',
    },
];

describe('PermissionsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url) => {
            if (String(url).includes('/api/v1/permissions')) {
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

        const wrapper = mount(PermissionsPage);

        await flushPromises();

        return wrapper;
    }

    it('lists built-in and custom Permissions for Tenant Admin', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="permissions-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Permissions');
        expect(wrapper.findAll('[data-testid="permission-row"]').length).toBe(2);
        expect(wrapper.text()).toContain('Manage Users');
        expect(wrapper.text()).toContain('Approve referrals');
        expect(wrapper.find('[data-testid="permission-edit-perm_1"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="permission-edit-perm_2"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="permission-delete-perm_1"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="permission-delete-perm_2"]').exists()).toBe(true);
    });

    it('shows access error for Teacher without fetching', async () => {
        const wrapper = await mountPage('teacher');

        expect(wrapper.find('[data-testid="permissions-error"]').text()).toContain('You don’t have access.');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('creates a custom Permission via POST', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/permissions') && method === 'GET') {
                return jsonResponse({ data: samplePermissions });
            }

            if (path.includes('/api/v1/permissions') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'perm_new',
                            key: 'sign_ehcp',
                            label: 'Sign EHCP',
                            is_system: false,
                            group: 'Custom',
                        },
                    },
                    201,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="permissions-add-open"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="permission-key-input"]').setValue('sign_ehcp');
        await wrapper.find('[data-testid="permission-label-input"]').setValue('Sign EHCP');
        await wrapper.find('[data-testid="permissions-form"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/permissions' && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            key: 'sign_ehcp',
            label: 'Sign EHCP',
            description: null,
            group: 'Custom',
        });
        expect(wrapper.text()).toContain('Sign EHCP');
    });

    it('edits a built-in Permission via PATCH and locks the key', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/permissions') && method === 'GET') {
                return jsonResponse({ data: samplePermissions });
            }

            if (path.includes('/api/v1/permissions/perm_1') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        id: 'perm_1',
                        key: 'manage_users',
                        label: 'Administer Users',
                        is_system: true,
                        group: 'Administration',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="permission-edit-perm_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="permission-key-input"]').element.disabled).toBe(true);

        await wrapper.find('[data-testid="permission-label-input"]').setValue('Administer Users');
        await wrapper.find('[data-testid="permissions-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/permissions/perm_1'
                && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toMatchObject({
            key: 'manage_users',
            label: 'Administer Users',
        });
        expect(wrapper.text()).toContain('Administer Users');
    });

    it('filters Permissions by key', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="permissions-search"]').setValue('approve');
        await flushPromises();

        expect(wrapper.findAll('[data-testid="permission-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="permission-label"]').text()).toContain('Approve referrals');
    });

    it('deletes a built-in Permission', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/permissions') && method === 'GET') {
                return jsonResponse({ data: samplePermissions });
            }

            if (path.includes('/api/v1/permissions/perm_1') && method === 'DELETE') {
                return { ok: true, status: 204, async json() { return {}; }, clone() { return this; } };
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="permission-delete-perm_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="permissions-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Manage Users');
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
