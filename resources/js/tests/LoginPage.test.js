/** @vitest-environment jsdom */

import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { login, logout } from '../api/auth.js';
import LoginPage from '../pages/LoginPage.vue';
import { routes } from '../router/index.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

describe('login route', () => {
    it('registers /login and does not register a public register route', () => {
        const paths = routes.map((route) => route.path);

        expect(paths).toContain('/login');
        expect(paths).not.toContain('/register');
        expect(paths).not.toContain('/signup');
    });

    it('router source does not mention register or signup paths', () => {
        const routerSource = readFileSync(
            resolve(root, 'resources/js/router/index.js'),
            'utf8',
        );

        expect(routerSource.toLowerCase()).not.toMatch(/register|signup/);
    });
});

describe('auth api helper', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
    });

    it('fetches CSRF cookie then posts login with credentials and X-XSRF-TOKEN', async () => {
        document.cookie = 'XSRF-TOKEN='.concat(encodeURIComponent('test-xsrf-token'));

        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ message: 'Authenticated.' }),
            });

        vi.stubGlobal('fetch', fetchMock);

        const result = await login('staff@example.com', 'password');

        expect(result).toEqual({ ok: true });
        expect(fetchMock).toHaveBeenCalledTimes(2);

        expect(fetchMock.mock.calls[0][0]).toBe('/sanctum/csrf-cookie');
        expect(fetchMock.mock.calls[0][1]).toMatchObject({
            method: 'GET',
            credentials: 'include',
        });

        expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/login');
        expect(fetchMock.mock.calls[1][1]).toMatchObject({
            method: 'POST',
            credentials: 'include',
            headers: expect.objectContaining({
                'X-XSRF-TOKEN': 'test-xsrf-token',
                Accept: 'application/json',
                'Content-Type': 'application/json',
            }),
            body: JSON.stringify({
                email: 'staff@example.com',
                password: 'password',
            }),
        });
    });

    it('throws when logout response is not ok', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({ ok: false, status: 401 });

        vi.stubGlobal('fetch', fetchMock);

        await expect(logout()).rejects.toThrow(/sign out/i);
    });
});

describe('LoginPage', () => {
    let router;

    beforeEach(async () => {
        router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', name: 'home', component: { template: '<div>home</div>' } },
                { path: '/login', name: 'login', component: LoginPage },
            ],
        });

        await router.push('/login');
        await router.isReady();
    });

    afterEach(async () => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        const { useSession } = await import('../features/auth/session.js');
        useSession().setUser(null);
    });

    it('shows a generic error when credentials fail', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({
                ok: false,
                json: async () => ({
                    message: 'These credentials do not match our records.',
                    code: 'authentication_failed',
                }),
            });

        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(LoginPage, {
            global: {
                plugins: [router],
            },
        });

        await wrapper.find('#email').setValue('unknown@example.com');
        await wrapper.find('#password').setValue('wrong-password');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="login-error"]').text()).toContain(
            'These credentials do not match our records.',
        );
        expect(wrapper.find('[data-testid="login-error"]').text()).not.toMatch(
            /not found|no account|unknown email/i,
        );
    });

    it('shows CSRF init failure distinctly from credentials failure', async () => {
        const fetchMock = vi.fn().mockResolvedValueOnce({ ok: false, status: 500 });

        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(LoginPage, {
            global: {
                plugins: [router],
            },
        });

        await wrapper.find('#email').setValue('staff@example.com');
        await wrapper.find('#password').setValue('password');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="login-error"]').text()).toContain(
            'Unable to initialise CSRF protection.',
        );
    });

    it('navigates home after a successful sign-in', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ message: 'Authenticated.' }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                clone: () => ({
                    json: async () => ({
                        data: {
                            id: 'usr_1',
                            name: 'Staff User',
                            email: 'staff@example.com',
                            role: 'teacher',
                            tenant_id: 'ten_1',
                        },
                    }),
                }),
                json: async () => ({
                    data: {
                        id: 'usr_1',
                        name: 'Staff User',
                        email: 'staff@example.com',
                        role: 'teacher',
                        tenant_id: 'ten_1',
                    },
                }),
            });

        vi.stubGlobal('fetch', fetchMock);

        const push = vi.spyOn(router, 'push');

        const wrapper = mount(LoginPage, {
            global: {
                plugins: [router],
            },
        });

        await wrapper.find('#email').setValue('staff@example.com');
        await wrapper.find('#password').setValue('password');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(push).toHaveBeenCalledWith({ name: 'home' });
        expect(wrapper.find('[data-testid="login-error"]').exists()).toBe(false);

        const { useSession } = await import('../features/auth/session.js');
        expect(useSession().role.value).toBe('teacher');
    });
});
