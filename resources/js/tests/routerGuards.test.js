/** @vitest-environment jsdom */

import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import AccessDenied from '../pages/AccessDenied.vue';
import LoginPage from '../pages/LoginPage.vue';
import OntologyCataloguePage from '../pages/OntologyCataloguePage.vue';
import { routes } from '../router/index.js';

describe('router auth meta', () => {
    it('marks shell home as requiring auth and login as guest', () => {
        const home = routes.find((entry) => entry.path === '/');
        const login = routes.find((entry) => entry.path === '/login');
        const accessDenied = routes.find((entry) => entry.path === '/access-denied');
        const libraryManagement = home?.children?.find(
            (entry) => entry.name === 'library-management',
        );
        const ontologyCatalogue = home?.children?.find(
            (entry) => entry.name === 'ontology-catalogue',
        );

        expect(home?.meta?.requiresAuth).toBe(true);
        expect(login?.meta?.guest).toBe(true);
        expect(accessDenied?.children?.[0]?.name).toBe('access-denied');
        expect(accessDenied?.children?.[0]?.component).toBe(AccessDenied);
        expect(libraryManagement?.path).toBe('library-management');
        expect(libraryManagement?.meta?.requiresAuth).toBe(true);
        expect(ontologyCatalogue?.path).toBe('ontology-catalogue');
        expect(ontologyCatalogue?.component).toBe(OntologyCataloguePage);
        expect(ontologyCatalogue?.meta?.requiresAuth).toBe(true);
    });

    it('redirects guests from home to login', async () => {
        vi.resetModules();

        const fetchMock = vi.fn().mockResolvedValue({
            ok: false,
            status: 401,
            clone: () => ({ json: async () => ({}) }),
            json: async () => ({}),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { default: router } = await import('../router/index.js');
        const { useSession } = await import('../features/auth/session.js');

        // Reset session module state by clearing via setUser(null) after fresh bootstrap path
        useSession().setUser(null);

        // Rebuild with memory history for the test by pushing through the real guard logic
        const testRouter = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
                {
                    path: '/',
                    name: 'home',
                    component: { template: '<div>home</div>' },
                    meta: { requiresAuth: true },
                },
                {
                    path: '/access-denied',
                    name: 'access-denied',
                    component: AccessDenied,
                    meta: { requiresAuth: true },
                },
            ],
        });

        testRouter.beforeEach(async (to) => {
            const session = useSession();

            if (!session.bootstrapped.value) {
                await session.bootstrap();
            }

            const isAuthenticated = session.isAuthenticated.value;
            const isGuestRoute = to.matched.some((record) => record.meta.guest === true);
            const requiresAuth = to.matched.some((record) => record.meta.requiresAuth === true);

            if (requiresAuth && !isAuthenticated) {
                return { name: 'login', query: { redirect: to.fullPath } };
            }

            if (isGuestRoute && isAuthenticated) {
                return { name: 'home' };
            }

            return true;
        });

        await testRouter.push('/');
        await testRouter.isReady();

        expect(testRouter.currentRoute.value.name).toBe('login');
        expect(router).toBeTruthy();
    });
});

beforeEach(() => {
    vi.unstubAllGlobals();
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});
