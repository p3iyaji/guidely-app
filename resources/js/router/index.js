import { createRouter, createWebHistory } from 'vue-router';
import { useSession } from '../features/auth/session';
import AppShell from '../layouts/AppShell.vue';
import GuestLayout from '../layouts/GuestLayout.vue';
import AccessDenied from '../pages/AccessDenied.vue';
import ComingSoonPage from '../pages/ComingSoonPage.vue';
import HomeDashboard from '../pages/HomeDashboard.vue';
import LoginPage from '../pages/LoginPage.vue';

/**
 * Stub page for unfinished domains (UK English empty state — not fake data).
 *
 * @param {string} title
 */
function stub(title) {
    return {
        component: ComingSoonPage,
        meta: { title, requiresAuth: true },
    };
}

/**
 * Product SPA routes. Keep wireframe parent/LMS/clinician/EduConnect IA out of this table.
 * Vue Role menus are UX only — never treat nav visibility as API authorisation.
 */
const routes = [
    {
        path: '/login',
        component: GuestLayout,
        meta: { guest: true },
        children: [
            {
                path: '',
                name: 'login',
                component: LoginPage,
                meta: { guest: true },
            },
        ],
    },
    {
        path: '/access-denied',
        name: 'access-denied',
        component: AccessDenied,
        meta: { requiresAuth: true },
    },
    {
        path: '/',
        component: AppShell,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'home',
                component: HomeDashboard,
                meta: { requiresAuth: true },
            },
            {
                path: 'pupils',
                name: 'pupils',
                ...stub('My Pupils'),
            },
            {
                path: 'capture',
                name: 'capture',
                ...stub('Capture'),
            },
            {
                path: 'drafts',
                name: 'drafts',
                ...stub('Drafts'),
            },
            {
                path: 'settings',
                name: 'settings',
                ...stub('Settings'),
            },
            {
                path: 'review-cycles',
                name: 'review-cycles',
                ...stub('Review Cycles'),
            },
            {
                path: 'gaps',
                name: 'gaps',
                ...stub('Gaps'),
            },
            {
                path: 'outputs',
                name: 'outputs',
                ...stub('Outputs'),
            },
            {
                path: 'school-report',
                name: 'school-report',
                ...stub('School Report'),
            },
            {
                path: 'import',
                name: 'import',
                ...stub('Import'),
            },
            {
                // Coming soon stub is OK for 1.8; FeatureFlaggedEmpty when flag off lands with later Trust work / API 403.
                path: 'trust-dashboard',
                name: 'trust-dashboard',
                ...stub('Trust Dashboard'),
            },
            {
                path: 'schools',
                name: 'schools',
                ...stub('Schools'),
            },
            {
                path: 'alerts',
                name: 'alerts',
                ...stub('Alerts'),
            },
            {
                path: 'users',
                name: 'users',
                ...stub('Users'),
            },
            {
                path: 'connectors',
                name: 'connectors',
                ...stub('Connectors'),
            },
            {
                path: 'feature-flags',
                name: 'feature-flags',
                ...stub('Feature flags'),
            },
            {
                path: 'pilot-toolkit',
                name: 'pilot-toolkit',
                ...stub('Pilot toolkit'),
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const session = useSession();

    if (!session.bootstrapped.value) {
        try {
            await session.bootstrap();
        } catch {
            return { name: 'login', query: { redirect: to.fullPath } };
        }
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

export default router;
export { routes };
