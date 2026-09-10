/** @vitest-environment jsdom */

import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { describe, expect, it } from 'vitest';
import AccessDenied from '../pages/AccessDenied.vue';
import { useSession } from '../features/auth/session.js';
import { routes } from '../router/index.js';

describe('access-denied route', () => {
    it('registers /access-denied bound to AccessDenied inside the app shell', () => {
        const route = routes.find((entry) => entry.path === '/access-denied');

        expect(route).toBeDefined();
        expect(route?.children?.[0]?.name).toBe('access-denied');
        expect(route?.children?.[0]?.component).toBe(AccessDenied);
    });
});

describe('AccessDenied', () => {
    async function mountPage(role = 'teacher') {
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
                { path: '/', name: 'home', component: { template: '<div />' } },
                { path: '/pupils', name: 'pupils', component: { template: '<div />' } },
                { path: '/access-denied', name: 'access-denied', component: AccessDenied },
            ],
        });

        await router.push('/access-denied');
        await router.isReady();

        return mount(AccessDenied, {
            global: {
                plugins: [router],
            },
        });
    }

    it('shows exact You don’t have access copy', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('h1').text()).toBe('You don’t have access');
        expect(wrapper.find('[data-testid="access-denied"]').attributes('role')).toBe('alert');
        expect(wrapper.find('[data-testid="access-denied-home"]').attributes('href')).toBe('/');
        expect(wrapper.find('[data-testid="access-denied-pupils"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Contact your Tenant Admin');
    });

    it('offers Tenant Admin a Pupils path instead of asking them to contact themselves', async () => {
        const wrapper = await mountPage('tenant_admin');

        expect(wrapper.find('h1').text()).toBe('You don’t have access');
        expect(wrapper.find('[data-testid="access-denied-home"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="access-denied-pupils"]').attributes('href')).toBe('/pupils');
        expect(wrapper.text()).toContain('manage Pupil working records from Pupils');
        expect(wrapper.text()).not.toContain('Contact your Tenant Admin');
    });
});
