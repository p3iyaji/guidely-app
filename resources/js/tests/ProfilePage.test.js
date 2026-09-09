/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import ProfilePage from '../pages/ProfilePage.vue';

const sampleUser = {
    id: 'usr_1',
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    role: 'teacher',
    tenant_id: 'ten_1',
    school_ids: [],
};

describe('ProfilePage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/me' && method === 'GET') {
                return jsonResponse({ data: sampleUser });
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
    async function mountPage(role = 'teacher') {
        useSession().setUser({
            ...sampleUser,
            role,
        });

        const wrapper = mount(ProfilePage);
        await flushPromises();

        return wrapper;
    }

    it('loads the signed-in Profile without Coming soon', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="profile-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Profile');
        expect(wrapper.find('[data-testid="profile-name"]').text()).toBe('Ada Lovelace');
        expect(wrapper.find('[data-testid="profile-email"]').text()).toBe('ada@example.com');
        expect(wrapper.find('[data-testid="profile-role"]').text()).toBe('Teacher');
        expect(wrapper.find('[data-testid="profile-form"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
    });

    it('opens Edit profile in a dialog and PATCHes /me', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/me' && method === 'GET') {
                return jsonResponse({ data: sampleUser });
            }

            if (path === '/api/v1/me' && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        ...sampleUser,
                        name: 'Ada King',
                        email: 'ada.king@example.com',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="profile-edit-open"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="profile-form"]');

        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(wrapper.find('[data-testid="profile-details"]').exists()).toBe(true);

        await wrapper.find('[data-testid="profile-name-input"]').setValue('Ada King');
        await wrapper.find('[data-testid="profile-email-input"]').setValue('ada.king@example.com');
        await wrapper.find('[data-testid="profile-form"] form').trigger('submit');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/me' && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            name: 'Ada King',
            email: 'ada.king@example.com',
        });
        expect(wrapper.find('[data-testid="profile-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="profile-name"]').text()).toBe('Ada King');
        expect(useSession().user.value?.name).toBe('Ada King');
    });

    it('shows validation errors in the profile dialog', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/me' && method === 'GET') {
                return jsonResponse({ data: sampleUser });
            }

            if (path === '/api/v1/me' && method === 'PATCH') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: { email: ['The email has already been taken.'] },
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="profile-edit-open"]').trigger('click');
        await wrapper.find('[data-testid="profile-email-input"]').setValue('taken@example.com');
        await wrapper.find('[data-testid="profile-form"] form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-email"]').text()).toContain('already been taken');
        expect(wrapper.find('[data-testid="profile-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="profile-email"]').text()).toBe('ada@example.com');
    });

    it('opens Change password in a dialog and PATCHes /me/password', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/me' && method === 'GET') {
                return jsonResponse({ data: sampleUser });
            }

            if (path === '/api/v1/me/password' && method === 'PATCH') {
                return jsonResponse({ data: sampleUser });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="profile-password-open"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="profile-password-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="profile-details"]').exists()).toBe(true);

        await wrapper.find('[data-testid="profile-current-password-input"]').setValue('password');
        await wrapper.find('[data-testid="profile-password-input"]').setValue('password123');
        await wrapper.find('[data-testid="profile-password-confirmation-input"]').setValue('password123');
        await wrapper.find('[data-testid="profile-password-form"] form').trigger('submit');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/me/password' && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            current_password: 'password',
            password: 'password123',
            password_confirmation: 'password123',
        });
        expect(wrapper.find('[data-testid="profile-password-form"]').exists()).toBe(false);
    });

    it('blocks password submit when confirmation does not match', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="profile-password-open"]').trigger('click');
        await wrapper.find('[data-testid="profile-current-password-input"]').setValue('password');
        await wrapper.find('[data-testid="profile-password-input"]').setValue('password123');
        await wrapper.find('[data-testid="profile-password-confirmation-input"]').setValue('other');
        await wrapper.find('[data-testid="profile-password-form"] form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-password_confirmation"]').text()).toContain('does not match');

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/me/password' && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeUndefined();
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
