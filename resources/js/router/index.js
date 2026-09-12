import { createRouter, createWebHistory } from 'vue-router';
import { useSession } from '../features/auth/session';
import AppShell from '../layouts/AppShell.vue';
import GuestLayout from '../layouts/GuestLayout.vue';
import AccessDenied from '../pages/AccessDenied.vue';
import AuditEventsPage from '../pages/AuditEventsPage.vue';
import ConnectorsPage from '../pages/ConnectorsPage.vue';
import EvidenceBasePage from '../pages/EvidenceBasePage.vue';
import FeatureFlagsPage from '../pages/FeatureFlagsPage.vue';
import GapsPage from '../pages/GapsPage.vue';
import HomeDashboard from '../pages/HomeDashboard.vue';
import LibraryManagementPage from '../pages/LibraryManagementPage.vue';
import LoginPage from '../pages/LoginPage.vue';
import NeedTermsPage from '../pages/NeedTermsPage.vue';
import OntologyCataloguePage from '../pages/OntologyCataloguePage.vue';
import PilotToolkitPage from '../pages/PilotToolkitPage.vue';
import ProfilePage from '../pages/ProfilePage.vue';
import PupilsPage from '../pages/PupilsPage.vue';
import PermissionsPage from '../pages/PermissionsPage.vue';
import ProvisionTermsPage from '../pages/ProvisionTermsPage.vue';
import ImportPage from '../pages/ImportPage.vue';
import CapturePage from '../pages/CapturePage.vue';
import DraftsPage from '../pages/DraftsPage.vue';
import ReviewCyclesPage from '../pages/ReviewCyclesPage.vue';
import RolesPage from '../pages/RolesPage.vue';
import OutputsPage from '../pages/OutputsPage.vue';
import SchoolReportPage from '../pages/SchoolReportPage.vue';
import TrustDashboardPage from '../pages/TrustDashboardPage.vue';
import AlertsPage from '../pages/AlertsPage.vue';
import SafeguardingContextPage from '../pages/SafeguardingContextPage.vue';
import SchoolsPage from '../pages/SchoolsPage.vue';
import SettingsPage from '../pages/SettingsPage.vue';
import UsersPage from '../pages/UsersPage.vue';

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
        component: AppShell,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'access-denied',
                component: AccessDenied,
                meta: { title: 'Access denied', requiresAuth: true },
            },
        ],
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
                component: PupilsPage,
                meta: { title: 'Pupils', requiresAuth: true },
            },
            {
                path: 'pupils/:id',
                name: 'pupil-detail',
                component: EvidenceBasePage,
                meta: { title: 'Evidence Base', requiresAuth: true },
            },
            {
                path: 'capture',
                name: 'capture',
                component: CapturePage,
                meta: { title: 'Capture Observation', requiresAuth: true },
            },
            {
                path: 'drafts',
                name: 'drafts',
                component: DraftsPage,
                meta: { title: 'Drafts', requiresAuth: true },
            },
            {
                path: 'settings',
                name: 'settings',
                component: SettingsPage,
                meta: { title: 'Settings', requiresAuth: true },
            },
            {
                path: 'profile',
                name: 'profile',
                component: ProfilePage,
                meta: { title: 'Profile', requiresAuth: true },
            },
            {
                path: 'review-cycles',
                name: 'review-cycles',
                component: ReviewCyclesPage,
                meta: { title: 'Review Cycles', requiresAuth: true },
            },
            {
                path: 'gaps',
                name: 'gaps',
                component: GapsPage,
                meta: { title: 'Gaps', requiresAuth: true },
            },
            {
                path: 'outputs',
                name: 'outputs',
                component: OutputsPage,
                meta: { title: 'Outputs', requiresAuth: true },
            },
            {
                path: 'school-report',
                name: 'school-report',
                component: SchoolReportPage,
                meta: { title: 'School Report', requiresAuth: true },
            },
            {
                path: 'import',
                name: 'import',
                component: ImportPage,
                meta: { title: 'Import', requiresAuth: true },
            },
            {
                path: 'trust-dashboard',
                name: 'trust-dashboard',
                component: TrustDashboardPage,
                meta: { title: 'Trust Dashboard', requiresAuth: true },
            },
            {
                path: 'schools',
                name: 'schools',
                component: SchoolsPage,
                meta: { title: 'Schools', requiresAuth: true },
            },
            {
                path: 'alerts',
                name: 'alerts',
                component: AlertsPage,
                meta: { title: 'Alerts', requiresAuth: true },
            },
            {
                path: 'safeguarding-context',
                name: 'safeguarding-context',
                component: SafeguardingContextPage,
                meta: { title: 'Safeguarding context', requiresAuth: true },
            },
            {
                path: 'users',
                name: 'users',
                component: UsersPage,
                meta: { title: 'Users', requiresAuth: true },
            },
            {
                path: 'roles',
                name: 'roles',
                component: RolesPage,
                meta: { title: 'Role catalogue', requiresAuth: true },
            },
            {
                path: 'permissions',
                name: 'permissions',
                component: PermissionsPage,
                meta: { title: 'Permission catalogue', requiresAuth: true },
            },
            {
                path: 'audit-events',
                name: 'audit-events',
                component: AuditEventsPage,
                meta: { title: 'Audit events', requiresAuth: true },
            },
            {
                path: 'connectors',
                name: 'connectors',
                component: ConnectorsPage,
                meta: { title: 'Connectors', requiresAuth: true },
            },
            {
                path: 'feature-flags',
                name: 'feature-flags',
                component: FeatureFlagsPage,
                meta: { title: 'Feature flags', requiresAuth: true },
            },
            {
                path: 'provision-terms',
                name: 'provision-terms',
                component: ProvisionTermsPage,
                meta: { title: 'Provision terms', requiresAuth: true },
            },
            {
                path: 'need-terms',
                name: 'need-terms',
                component: NeedTermsPage,
                meta: { title: 'Need terms', requiresAuth: true },
            },
            {
                path: 'ontology-catalogue',
                name: 'ontology-catalogue',
                component: OntologyCataloguePage,
                meta: { title: 'Ontology and Rule catalogue', requiresAuth: true },
            },
            {
                path: 'pilot-toolkit',
                name: 'pilot-toolkit',
                component: PilotToolkitPage,
                meta: { title: 'Pilot toolkit', requiresAuth: true },
            },
            {
                path: 'library-management',
                name: 'library-management',
                component: LibraryManagementPage,
                meta: { title: 'Library management', requiresAuth: true },
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
