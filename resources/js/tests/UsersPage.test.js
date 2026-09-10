/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import UsersPage from '../pages/UsersPage.vue';

const sampleUsers = [
    {
        id: 'usr_1',
        name: 'Alex Rivera',
        email: 'alex@example.com',
        role: 'teacher',
        school_ids: ['sch_1'],
        deactivated_at: null,
        external_id: null,
    },
    {
        id: 'usr_2',
        name: 'Jordan Lee',
        email: 'jordan@example.com',
        role: 'senco',
        school_ids: [],
        deactivated_at: null,
        external_id: 'ext-2',
    },
];

const sampleSchools = [
    { id: 'sch_1', name: 'Northbridge Primary', is_active: true },
    { id: 'sch_2', name: 'Oak Academy', is_active: true },
];

describe('UsersPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/users') && method === 'GET') {
                return jsonResponse({ data: sampleUsers });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            if (path.includes('/api/v1/users') && method === 'POST' && !path.includes('/deactivate')) {
                return jsonResponse(
                    {
                        data: {
                            id: 'usr_new',
                            name: 'Sam Patel',
                            email: 'sam@example.com',
                            role: 'teacher',
                            school_ids: ['sch_1'],
                            deactivated_at: null,
                            external_id: null,
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

        useSession().setUser({
            id: 'usr_admin',
            name: 'Tenant Admin',
            email: 'admin@example.com',
            role: 'tenant_admin',
            tenant_id: 'ten_1',
        });
    });

    afterEach(() => {
        useSession().setUser(null);
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    /**
     * @param {{ flush?: boolean }} [options]
     */
    async function mountPage(options = {}) {
        const { flush = true } = options;
        const wrapper = mount(UsersPage);

        if (flush) {
            await flushPromises();
        }

        return wrapper;
    }

    it('lists Users with Role, status, and School names', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="users-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Users');
        expect(wrapper.findAll('[data-testid="user-row"]').length).toBe(2);
        expect(wrapper.find('[data-testid="user-name"]').text()).toContain('Alex Rivera');
        expect(wrapper.find('[data-testid="user-email"]').text()).toContain('alex@example.com');
        expect(wrapper.find('[data-testid="user-role"]').text()).toContain('Teacher');
        expect(wrapper.find('[data-testid="user-status"]').text()).toContain('Active');
        expect(wrapper.find('[data-testid="user-schools"]').text()).toContain('Northbridge Primary');
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.find('[data-testid="users-form"]').exists()).toBe(false);
    });

    it('opens Add User in a dialog and keeps the empty state behind it', async () => {
        fetchMock.mockImplementation(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/users')) {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="users-add-cta"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="users-form"]');

        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(wrapper.find('[data-testid="users-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="modal-backdrop"]').exists()).toBe(true);
    });

    it('keeps the Users list visible while the Edit dialog is open and closes on backdrop click', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="user-edit-usr_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="users-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="users-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="users-form"]').text()).toContain('Edit User');

        await wrapper.find('[data-testid="modal-backdrop"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="users-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="users-list"]').exists()).toBe(true);
    });

    it('filters Users by name, email, and Role label', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="users-search"]').setValue('Jordan');
        await flushPromises();
        expect(wrapper.findAll('[data-testid="user-row"]').length).toBe(1);
        expect(wrapper.find('[data-testid="user-name"]').text()).toContain('Jordan Lee');

        await wrapper.find('[data-testid="users-search"]').setValue('SENCO');
        await flushPromises();
        expect(wrapper.findAll('[data-testid="user-row"]').length).toBe(1);

        await wrapper.find('[data-testid="users-search"]').setValue('');
        await flushPromises();
        expect(wrapper.findAll('[data-testid="user-row"]').length).toBe(2);
    });

    it('shows empty copy and Add User CTA', async () => {
        fetchMock.mockImplementation(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/users')) {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="users-empty"]').text()).toContain('No Users in this Tenant.');
        expect(wrapper.find('[data-testid="users-add-cta"]').exists()).toBe(true);
    });

    it('hides Trust Roles when trust_dashboard is off and includes them when on', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="users-add-open"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="user-role-authority-note"]').text()).toContain(
            'Only built-in Roles can be assigned',
        );
        expect(wrapper.find('[data-testid="user-role-authority-note"]').text()).toContain(
            'Permission mappings never grant access',
        );
        expect(wrapper.find('[data-testid="user-role-input"]').html()).not.toContain('Trust SEND Lead');
        wrapper.unmount();

        fetchMock.mockImplementation(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/users')) {
                return jsonResponse({ data: sampleUsers });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: true } });
            }

            return jsonResponse({});
        });

        const withTrust = await mountPage();
        await withTrust.find('[data-testid="users-add-open"]').trigger('click');
        await flushPromises();
        expect(withTrust.find('[data-testid="user-role-input"]').html()).toContain('Trust SEND Lead');
        expect(withTrust.find('[data-testid="user-role-input"]').html()).toContain('Trust Executive');
        withTrust.unmount();
    });

    it('selects Schools from an optional multi-select dropdown, not checkboxes', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="users-add-open"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="user-schools-trigger"]').text()).toContain('Select Schools (optional)');
        expect(wrapper.find('[data-testid="user-schools-input"] input[type="checkbox"]').exists()).toBe(false);

        await wrapper.find('[data-testid="user-schools-trigger"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="user-schools-listbox"]').attributes('role')).toBe('listbox');
        expect(wrapper.find('[data-testid="user-schools-listbox"]').attributes('aria-multiselectable')).toBe('true');

        await wrapper.find('[data-testid="user-school-sch_1"]').trigger('click');
        await wrapper.find('[data-testid="user-school-sch_2"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="user-schools-trigger"]').text()).toContain('Northbridge Primary');
        expect(wrapper.find('[data-testid="user-schools-trigger"]').text()).toContain('Oak Academy');
        expect(wrapper.find('[data-testid="user-school-sch_1"]').attributes('aria-selected')).toBe('true');
        expect(wrapper.find('[data-testid="user-schools-listbox"]').exists()).toBe(true);
    });

    it('creates a User via POST with trimmed body and selected School', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/users') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            if (path.includes('/api/v1/users') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'usr_new',
                            name: 'Sam Patel',
                            email: 'sam@example.com',
                            role: 'teacher',
                            school_ids: ['sch_1'],
                            deactivated_at: null,
                            external_id: null,
                        },
                    },
                    201,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="users-add-cta"]').trigger('click');
        await flushPromises();

        await wrapper.find('[data-testid="user-name-input"]').setValue('  Sam Patel  ');
        await wrapper.find('[data-testid="user-email-input"]').setValue('  sam@example.com  ');
        await wrapper.find('[data-testid="user-password-input"]').setValue('password123');
        await wrapper.find('[data-testid="user-password-confirm-input"]').setValue('password123');
        await wrapper.find('[data-testid="user-role-input"]').setValue('teacher');
        await wrapper.find('[data-testid="user-schools-trigger"]').trigger('click');
        await wrapper.find('[data-testid="user-school-sch_1"]').trigger('click');
        await wrapper.find('[data-testid="users-form"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/users' && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            name: 'Sam Patel',
            email: 'sam@example.com',
            role: 'teacher',
            school_ids: ['sch_1'],
            external_id: null,
            password: 'password123',
        });
        expect(wrapper.find('[data-testid="users-list"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Sam Patel');
    });

    it('shows 422 inline field errors on Add User', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/users') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            if (path.includes('/api/v1/users') && method === 'POST') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: {
                            email: ['The email has already been taken.'],
                        },
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="users-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="user-name-input"]').setValue('Sam');
        await wrapper.find('[data-testid="user-email-input"]').setValue('taken@example.com');
        await wrapper.find('[data-testid="user-password-input"]').setValue('password123');
        await wrapper.find('[data-testid="user-password-confirm-input"]').setValue('password123');
        await wrapper.find('[data-testid="users-form"] form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-email"]').text()).toContain('already been taken');
        expect(wrapper.find('[data-testid="users-form"]').exists()).toBe(true);
    });

    it('blocks create when password confirmation does not match', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="users-add-open"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="user-name-input"]').setValue('Sam');
        await wrapper.find('[data-testid="user-email-input"]').setValue('sam@example.com');
        await wrapper.find('[data-testid="user-password-input"]').setValue('password123');
        await wrapper.find('[data-testid="user-password-confirm-input"]').setValue('different');
        await wrapper.find('[data-testid="users-form"] form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-password_confirmation"]').text()).toContain('does not match');
        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/users' && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );
        expect(postCall).toBeUndefined();
    });

    it('updates a User via PATCH', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/users') && method === 'GET') {
                return jsonResponse({ data: sampleUsers });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            if (path.includes('/api/v1/users/usr_1') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        ...sampleUsers[0],
                        name: 'Alex Updated',
                        school_ids: ['sch_1', 'sch_2'],
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="user-edit-usr_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="user-name-input"]').setValue('Alex Updated');
        await wrapper.find('[data-testid="user-schools-trigger"]').trigger('click');
        await wrapper.find('[data-testid="user-school-sch_2"]').trigger('click');
        await wrapper.find('[data-testid="users-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/users/usr_1' && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toMatchObject({
            name: 'Alex Updated',
            email: 'alex@example.com',
            role: 'teacher',
            school_ids: ['sch_1', 'sch_2'],
        });
        expect(wrapper.text()).toContain('Alex Updated');
    });

    it('resets a password via PATCH /users/{id}/password', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/users') && method === 'GET') {
                return jsonResponse({ data: sampleUsers });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            if (path.includes('/api/v1/users/usr_1/password') && method === 'PATCH') {
                return jsonResponse({ data: sampleUsers[0] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="user-password-usr_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="user-reset-password-input"]').setValue('newpass123');
        await wrapper.find('[data-testid="user-reset-password-confirm-input"]').setValue('newpass123');
        await wrapper.find('[data-testid="users-password-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(([url, options]) =>
            String(url).includes('/api/v1/users/usr_1/password')
            && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({ password: 'newpass123' });
        expect(wrapper.find('[data-testid="users-password-form"]').exists()).toBe(false);
    });

    it('deactivates a User via POST and shows Deactivated status', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/users') && method === 'GET') {
                return jsonResponse({ data: sampleUsers });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            if (path.includes('/api/v1/users/usr_1/deactivate') && method === 'POST') {
                return jsonResponse({
                    data: {
                        ...sampleUsers[0],
                        deactivated_at: '2026-09-08T12:00:00+00:00',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="user-deactivate-usr_1"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="users-deactivate-confirm"]').exists()).toBe(true);

        await wrapper.find('[data-testid="users-deactivate-confirm-submit"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Deactivated');
        expect(wrapper.find('[data-testid="user-edit-usr_1"]').attributes('disabled')).toBeDefined();
    });

    it('shows last Tenant Admin 422 message on deactivate', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/users') && method === 'GET') {
                return jsonResponse({ data: sampleUsers });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({ data: sampleSchools });
            }

            if (path.includes('/api/v1/tenant/feature-flags')) {
                return jsonResponse({ data: { trust_dashboard: false } });
            }

            if (path.includes('/deactivate') && method === 'POST') {
                return jsonResponse(
                    {
                        message: 'Cannot deactivate the last active Tenant Admin for this Tenant.',
                        code: 'last_tenant_admin',
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="user-deactivate-usr_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="users-deactivate-confirm-submit"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="users-form-error"]').text()).toContain('last active Tenant Admin');
        expect(wrapper.find('[data-testid="users-deactivate-confirm"]').exists()).toBe(true);
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
