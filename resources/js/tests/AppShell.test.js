/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';

vi.mock('../features/evidence/startOfflineFlushListener.js', () => ({
    startOfflineFlushListener: vi.fn(() => () => {}),
}));

import { useSession } from '../features/auth/session.js';
import { startOfflineFlushListener } from '../features/evidence/startOfflineFlushListener.js';
import { isNavItemActive } from '../features/shell/isNavItemActive.js';
import {
    allNavTargets,
    canSearchReviewCycles,
    navItemsForRole,
    navLabelsForRole,
    TEACHER_SUPPORT_BOTTOM_NAV,
    usesTeacherSupportBottomNav,
} from '../features/shell/navByRole.js';
import AppShell from '../layouts/AppShell.vue';
import BottomNav from '../shared/ui/BottomNav.vue';
import ButtonOutline from '../shared/ui/ButtonOutline.vue';
import ButtonPrimary from '../shared/ui/ButtonPrimary.vue';
import ButtonSecondary from '../shared/ui/ButtonSecondary.vue';
import Card from '../shared/ui/Card.vue';
import KpiCard from '../shared/ui/KpiCard.vue';
import LoadingSkeleton from '../shared/ui/LoadingSkeleton.vue';
import StatusPill from '../shared/ui/StatusPill.vue';
import TopBar from '../shared/ui/TopBar.vue';
import HomeDashboard from '../pages/HomeDashboard.vue';
import PilotToolkitPage from '../pages/PilotToolkitPage.vue';
import PupilsPage from '../pages/PupilsPage.vue';
import ImportPage from '../pages/ImportPage.vue';
import CapturePage from '../pages/CapturePage.vue';
import DraftsPage from '../pages/DraftsPage.vue';
import EvidenceBasePage from '../pages/EvidenceBasePage.vue';
import GapsPage from '../pages/GapsPage.vue';
import ReviewCyclesPage from '../pages/ReviewCyclesPage.vue';
import OutputsPage from '../pages/OutputsPage.vue';
import SchoolReportPage from '../pages/SchoolReportPage.vue';
import ComingSoonPage from '../pages/ComingSoonPage.vue';
import ConnectorsPage from '../pages/ConnectorsPage.vue';
import TrustDashboardPage from '../pages/TrustDashboardPage.vue';
import AlertsPage from '../pages/AlertsPage.vue';
import { routes as productionRoutes } from '../router/index.js';

describe('Role nav IA', () => {
    it('lists Teacher sidebar items without Messages', () => {
        const labels = navLabelsForRole('teacher');

        expect(labels).toEqual([
            'Dashboard',
            'My Pupils',
            'Capture',
            'Drafts',
            'Settings',
        ]);
        expect(labels.join(' ')).not.toMatch(/messages/i);
    });

    it('lists Support Staff the same as Teacher without Messages', () => {
        expect(navLabelsForRole('support_staff')).toEqual(navLabelsForRole('teacher'));
        expect(navLabelsForRole('support_staff').join(' ')).not.toMatch(/messages/i);
    });

    it('lists SENCO sidebar items from EXPERIENCE', () => {
        expect(navLabelsForRole('senco')).toEqual([
            'Dashboard',
            'Pupils',
            'Capture',
            'Review Cycles',
            'Gaps',
            'Outputs',
            'School Report',
            'Alerts',
            'Import',
            'Settings',
        ]);
    });

    it('lists School Leader sidebar items', () => {
        expect(navLabelsForRole('school_leader')).toEqual([
            'Dashboard',
            'School Report',
            'Review Cycles',
            'Settings',
        ]);
    });

    it('lists Trust SEND Lead sidebar items', () => {
        expect(navLabelsForRole('trust_send_lead')).toEqual([
            'Trust Dashboard',
            'Schools',
            'Alerts',
            'Settings',
        ]);
    });

    it('lists Trust Executive sidebar items', () => {
        expect(navLabelsForRole('trust_executive')).toEqual([
            'Trust Dashboard',
            'Schools',
            'Alerts',
            'Settings',
        ]);
    });

    it('lists Platform Operator sidebar items', () => {
        expect(navLabelsForRole('platform_operator')).toEqual([
            'Dashboard',
            'Pilot toolkit',
            'Settings',
        ]);
    });

    it('lists Tenant Admin sidebar items', () => {
        expect(navLabelsForRole('tenant_admin')).toEqual([
            'Users',
            'Schools',
            'Pupils',
            'Import',
            'Connectors',
            'Feature flags',
            'Pilot toolkit',
            'Settings',
        ]);
    });

    it('normalises Role casing when resolving nav', () => {
        expect(navLabelsForRole('Teacher')).toEqual(navLabelsForRole('teacher'));
        expect(navItemsForRole('SENCO')).toEqual(navItemsForRole('senco'));
        expect(usesTeacherSupportBottomNav('Support_Staff')).toBe(true);
    });

    it('enables Teacher/Support bottom nav Home My Pupils Capture Drafts More', () => {
        expect(usesTeacherSupportBottomNav('teacher')).toBe(true);
        expect(usesTeacherSupportBottomNav('support_staff')).toBe(true);
        expect(usesTeacherSupportBottomNav('senco')).toBe(false);

        expect(TEACHER_SUPPORT_BOTTOM_NAV.map((item) => item.label)).toEqual([
            'Home',
            'My Pupils',
            'Capture',
            'Drafts',
            'More',
        ]);
    });

    it('returns empty nav for unknown roles', () => {
        expect(navItemsForRole('unknown')).toEqual([]);
        expect(navItemsForRole(null)).toEqual([]);
    });

    it('enables Review Cycle search only for Roles that list Review Cycles', () => {
        expect(canSearchReviewCycles('senco')).toBe(true);
        expect(canSearchReviewCycles('school_leader')).toBe(true);
        expect(canSearchReviewCycles('teacher')).toBe(false);
        expect(canSearchReviewCycles('support_staff')).toBe(false);
        expect(canSearchReviewCycles('tenant_admin')).toBe(false);
        expect(canSearchReviewCycles(null)).toBe(false);
    });

    it('registers every nav to on production routes', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        for (const to of allNavTargets()) {
            const resolved = router.resolve(to);
            expect(resolved.matched.length, `missing production route for nav target ${to}`).toBeGreaterThan(0);
        }
    });

    it('uses PilotToolkitPage for production pilot-toolkit route', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/pilot-toolkit');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(PilotToolkitPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses PupilsPage for production pupils route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const list = router.resolve('/pupils');
        const listLeaf = list.matched[list.matched.length - 1];

        expect(listLeaf?.components?.default ?? listLeaf?.component).toBe(PupilsPage);
        expect(listLeaf?.components?.default ?? listLeaf?.component).not.toBe(ComingSoonPage);

        const detail = router.resolve('/pupils/pup_1');
        const detailLeaf = detail.matched[detail.matched.length - 1];

        expect(detailLeaf?.components?.default ?? detailLeaf?.component).toBe(EvidenceBasePage);
        expect(detailLeaf?.components?.default ?? detailLeaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses ImportPage for production import route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/import');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(ImportPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses CapturePage for production capture route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/capture');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(CapturePage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses DraftsPage for production drafts route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/drafts');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(DraftsPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses GapsPage for production gaps route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/gaps');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(GapsPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses ReviewCyclesPage for production review-cycles route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/review-cycles');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(ReviewCyclesPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses OutputsPage for production outputs route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/outputs');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(OutputsPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses SchoolReportPage for production school-report route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/school-report');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(SchoolReportPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses TrustDashboardPage for production trust-dashboard route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/trust-dashboard');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(TrustDashboardPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses AlertsPage for production alerts route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/alerts');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(AlertsPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });

    it('uses ConnectorsPage for production connectors route (not ComingSoon)', () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: productionRoutes,
        });

        const resolved = router.resolve('/connectors');
        const leaf = resolved.matched[resolved.matched.length - 1];

        expect(leaf?.components?.default ?? leaf?.component).toBe(ConnectorsPage);
        expect(leaf?.components?.default ?? leaf?.component).not.toBe(ComingSoonPage);
    });
});

describe('isNavItemActive', () => {
    it('guards empty to and matches exact / nested paths', () => {
        expect(isNavItemActive({ path: '/pupils' }, '')).toBe(false);
        expect(isNavItemActive({ path: '/pupils' }, null)).toBe(false);
        expect(isNavItemActive({ path: '/' }, '/')).toBe(true);
        expect(isNavItemActive({ path: '/pupils' }, '/')).toBe(false);
        expect(isNavItemActive({ path: '/pupils' }, '/pupils')).toBe(true);
        expect(isNavItemActive({ path: '/pupils/1' }, '/pupils')).toBe(true);
    });
});

describe('shared primitives smoke', () => {
    it('renders Card, KpiCard placeholders, buttons, StatusPill, and LoadingSkeleton', () => {
        expect(mount(Card, { slots: { default: 'Body' } }).text()).toContain('Body');

        const kpi = mount(KpiCard, { props: { label: 'Open gaps', value: null } });
        expect(kpi.find('[data-testid="kpi-value"]').text()).toBe('—');
        expect(kpi.text()).toContain('Open gaps');
        expect(kpi.find('[data-testid="kpi-card-select"]').exists()).toBe(false);

        expect(mount(ButtonPrimary, { slots: { default: 'Save' } }).text()).toBe('Save');
        expect(mount(ButtonSecondary, { slots: { default: 'Learn more' } }).text()).toBe('Learn more');
        expect(mount(ButtonOutline, { slots: { default: 'Cancel' } }).text()).toBe('Cancel');

        const pill = mount(StatusPill, { props: { status: 'gaps' } });
        expect(pill.text()).toContain('Gaps');

        const evaluating = mount(StatusPill, { props: { status: 'evaluating' } });
        expect(evaluating.text()).toContain('Evaluating');

        expect(mount(LoadingSkeleton).attributes('role')).toBe('status');
    });

    it('TopBar submits scoped search to the Review Cycles due list', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/review-cycles', name: 'review-cycles', component: { template: '<div />' } },
            ],
        });
        await router.push('/');
        await router.isReady();

        const wrapper = mount(TopBar, {
            props: { userName: 'Ada Lovelace', canSearchReviewCycles: true },
            global: { plugins: [router] },
        });

        const search = wrapper.find('[data-testid="search-stub"] input');
        expect(search.exists()).toBe(true);
        expect(search.attributes('placeholder')).toMatch(/Review Cycles/i);
        expect(search.attributes('readonly')).toBeUndefined();
        expect(wrapper.find('[data-testid="avatar"]').text()).toBe('AL');

        await search.setValue('Maya');
        await wrapper.find('[data-testid="search-stub"]').trigger('submit');
        await flushPromises();

        expect(router.currentRoute.value.path).toBe('/review-cycles');
        expect(router.currentRoute.value.query.q).toBe('Maya');
    });

    it('TopBar hides Review Cycle search when the Role cannot list cycles', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/review-cycles', name: 'review-cycles', component: { template: '<div />' } },
            ],
        });
        await router.push('/');
        await router.isReady();

        const wrapper = mount(TopBar, {
            props: { userName: 'Ada Lovelace', canSearchReviewCycles: false },
            global: { plugins: [router] },
        });

        expect(wrapper.find('[data-testid="search-stub"]').exists()).toBe(false);
        expect(router.currentRoute.value.path).toBe('/');
    });

    it('TopBar account menu emits sign-out', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/review-cycles', name: 'review-cycles', component: { template: '<div />' } },
            ],
        });
        await router.push('/');
        await router.isReady();

        const wrapper = mount(TopBar, {
            props: { userName: 'Ada Lovelace' },
            attachTo: document.body,
            global: { plugins: [router] },
        });

        expect(wrapper.find('[data-testid="account-menu-panel"]').exists()).toBe(false);

        await wrapper.find('[data-testid="avatar"]').trigger('click');
        expect(wrapper.find('[data-testid="account-menu-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="sign-out"]').text()).toBe('Sign out');

        await wrapper.find('[data-testid="sign-out"]').trigger('click');
        expect(wrapper.emitted('sign-out')).toHaveLength(1);
        expect(wrapper.find('[data-testid="account-menu-panel"]').exists()).toBe(false);

        wrapper.unmount();
    });
});

describe('AppShell smoke', () => {
    afterEach(() => {
        useSession().setUser(null);
    });

    beforeEach(() => {
        Object.defineProperty(window, 'matchMedia', {
            writable: true,
            configurable: true,
            value: vi.fn().mockImplementation((query) => ({
                matches: false,
                media: query,
                onchange: null,
                addEventListener: vi.fn(),
                removeEventListener: vi.fn(),
                addListener: vi.fn(),
                removeListener: vi.fn(),
                dispatchEvent: vi.fn(),
            })),
        });
    });

    async function createShellRouter(extraChildren = []) {
        const stub = { template: '<div />' };
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    path: '/login',
                    name: 'login',
                    component: { template: '<div>login</div>' },
                },
                {
                    path: '/',
                    component: AppShell,
                    children: [
                        { path: '', component: { template: '<div>home</div>' } },
                        { path: 'pupils', component: stub },
                        { path: 'capture', component: stub },
                        { path: 'drafts', component: stub },
                        { path: 'settings', component: stub },
                        { path: 'review-cycles', component: stub },
                        { path: 'gaps', component: stub },
                        { path: 'outputs', component: stub },
                        { path: 'school-report', component: stub },
                        { path: 'alerts', component: stub },
                        { path: 'import', component: stub },
                        ...extraChildren,
                    ],
                },
            ],
        });

        await router.push('/');
        await router.isReady();

        return router;
    }

    async function mountShell(role, userName = 'Test User') {
        const router = await createShellRouter();

        return mount(AppShell, {
            props: { role, userName },
            global: {
                plugins: [router],
                stubs: {
                    BrandWordmark: { template: '<span>GuidelyEdu</span>' },
                },
            },
        });
    }

    it('renders top bar and Role sidebar for Teacher', async () => {
        const wrapper = await mountShell('teacher');

        expect(wrapper.find('[data-testid="app-shell"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="top-bar"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="sidebar"]').exists()).toBe(true);
        expect(startOfflineFlushListener).toHaveBeenCalled();

        const labels = wrapper.findAll('[data-testid="sidebar-item"]').map((node) => node.text());
        expect(labels).toContain('My Pupils');
        expect(labels.join(' ')).not.toMatch(/messages/i);
        expect(wrapper.find('[data-testid="bottom-nav"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="search-stub"]').exists()).toBe(false);
    });

    it('hides Teacher bottom nav pattern for SENCO', async () => {
        const wrapper = await mountShell('senco');

        expect(wrapper.find('[data-testid="bottom-nav"]').exists()).toBe(false);
        const labels = wrapper.findAll('[data-testid="sidebar-item"]').map((node) => node.text());
        expect(labels).toContain('Review Cycles');
        expect(labels).toContain('Gaps');
        expect(labels).toContain('Capture');
        expect(wrapper.find('[data-testid="search-stub"]').exists()).toBe(true);
    });

    it('derives Role sidebar and avatar initials from session when props empty', async () => {
        useSession().setUser({
            id: 'usr_1',
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            role: 'senco',
            tenant_id: 'ten_1',
        });

        const router = await createShellRouter();
        const wrapper = mount(AppShell, {
            global: {
                plugins: [router],
                stubs: {
                    BrandWordmark: { template: '<span>GuidelyEdu</span>' },
                },
            },
        });

        const sidebarLabels = wrapper
            .findAll('[data-testid="sidebar"]')[0]
            .findAll('[data-testid="sidebar-item"]')
            .map((node) => node.text());
        expect(sidebarLabels).toEqual([
            'Dashboard',
            'Pupils',
            'Capture',
            'Review Cycles',
            'Gaps',
            'Outputs',
            'School Report',
            'Alerts',
            'Import',
            'Settings',
        ]);
        expect(wrapper.find('[data-testid="avatar"]').text()).toBe('AL');
    });

    it('opens mobile panel on nav-toggle and closes on Escape or route change', async () => {
        const router = await createShellRouter();
        const wrapper = mount(AppShell, {
            props: { role: 'senco', userName: 'Test User' },
            attachTo: document.body,
            global: {
                plugins: [router],
                stubs: {
                    BrandWordmark: { template: '<span>GuidelyEdu</span>' },
                },
            },
        });

        const panel = wrapper.find('[data-testid="mobile-nav-panel"]');
        expect(panel.attributes('aria-hidden')).toBe('true');
        expect(wrapper.find('[data-testid="nav-toggle"]').attributes('aria-expanded')).toBe('false');

        await wrapper.find('[data-testid="nav-toggle"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="nav-toggle"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.find('[data-testid="mobile-nav-panel"]').attributes('aria-hidden')).toBe('false');
        expect(wrapper.find('[data-testid="nav-backdrop"]').exists()).toBe(true);

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();

        expect(wrapper.find('[data-testid="mobile-nav-panel"]').attributes('aria-hidden')).toBe('true');
        expect(wrapper.find('[data-testid="nav-toggle"]').attributes('aria-expanded')).toBe('false');

        await wrapper.find('[data-testid="nav-toggle"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="mobile-nav-panel"]').attributes('aria-hidden')).toBe('false');

        await router.push('/settings');
        await flushPromises();

        expect(wrapper.find('[data-testid="mobile-nav-panel"]').attributes('aria-hidden')).toBe('true');

        wrapper.unmount();
    });

    it('signs out from the account menu and returns to login', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ message: 'Logged out.' }) });
        vi.stubGlobal('fetch', fetchMock);

        useSession().setUser({
            id: 'usr_1',
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            role: 'senco',
            tenant_id: 'ten_1',
        });
        sessionStorage.setItem('guidely.hybrid_access_token', 'hybrid-token');

        const router = await createShellRouter();
        const wrapper = mount(AppShell, {
            props: { role: 'senco', userName: 'Ada Lovelace' },
            attachTo: document.body,
            global: {
                plugins: [router],
                stubs: {
                    BrandWordmark: { template: '<span>GuidelyEdu</span>' },
                },
            },
        });

        await wrapper.find('[data-testid="avatar"]').trigger('click');
        await wrapper.find('[data-testid="sign-out"]').trigger('click');
        await flushPromises();

        expect(useSession().isAuthenticated.value).toBe(false);
        expect(sessionStorage.getItem('guidely.hybrid_access_token')).toBeNull();
        expect(router.currentRoute.value.name).toBe('login');

        wrapper.unmount();
        vi.unstubAllGlobals();
    });
});

describe('HomeDashboard KPI placeholders', () => {
    it('shows placeholder KPI row', () => {
        const wrapper = mount(HomeDashboard);

        expect(wrapper.find('[data-testid="kpi-row"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="kpi-card"]').length).toBeGreaterThanOrEqual(3);
        expect(wrapper.findAll('[data-testid="kpi-value"]').every((node) => node.text() === '—')).toBe(true);
        expect(wrapper.find('[data-testid="kpi-card-select"]').exists()).toBe(false);
    });
});

describe('BottomNav labels', () => {
    it('renders Home My Pupils Capture Drafts More', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/pupils', component: { template: '<div />' } },
                { path: '/capture', component: { template: '<div />' } },
                { path: '/drafts', component: { template: '<div />' } },
                { path: '/settings', component: { template: '<div />' } },
            ],
        });

        await router.push('/');
        await router.isReady();

        const wrapper = mount(BottomNav, {
            props: { items: TEACHER_SUPPORT_BOTTOM_NAV },
            global: { plugins: [router] },
        });

        expect(wrapper.findAll('[data-testid="bottom-nav-item"]').map((n) => n.text())).toEqual([
            'Home',
            'My Pupils',
            'Capture',
            'Drafts',
            'More',
        ]);
    });
});
