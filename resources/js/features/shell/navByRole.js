/**
 * Role sidebar / bottom-nav IA from EXPERIENCE.md (Messages omitted).
 * Vue menus are presentation only — they never authorise APIs.
 */

/** @typedef {{ key: string, label: string, to: string }} NavItem */

/** @type {Record<string, NavItem[]>} */
export const NAV_BY_ROLE = {
    teacher: [
        { key: 'dashboard', label: 'Dashboard', to: '/' },
        { key: 'pupils', label: 'My Pupils', to: '/pupils' },
        { key: 'capture', label: 'Capture', to: '/capture' },
        { key: 'drafts', label: 'Drafts', to: '/drafts' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    support_staff: [
        { key: 'dashboard', label: 'Dashboard', to: '/' },
        { key: 'pupils', label: 'My Pupils', to: '/pupils' },
        { key: 'capture', label: 'Capture', to: '/capture' },
        { key: 'drafts', label: 'Drafts', to: '/drafts' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    senco: [
        { key: 'dashboard', label: 'Dashboard', to: '/' },
        { key: 'pupils', label: 'Pupils', to: '/pupils' },
        { key: 'review-cycles', label: 'Review Cycles', to: '/review-cycles' },
        { key: 'gaps', label: 'Gaps', to: '/gaps' },
        { key: 'outputs', label: 'Outputs', to: '/outputs' },
        { key: 'school-report', label: 'School Report', to: '/school-report' },
        { key: 'import', label: 'Import', to: '/import' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    school_leader: [
        { key: 'dashboard', label: 'Dashboard', to: '/' },
        { key: 'school-report', label: 'School Report', to: '/school-report' },
        { key: 'review-cycles', label: 'Review Cycles', to: '/review-cycles' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    trust_send_lead: [
        { key: 'trust-dashboard', label: 'Trust Dashboard', to: '/trust-dashboard' },
        { key: 'schools', label: 'Schools', to: '/schools' },
        { key: 'alerts', label: 'Alerts', to: '/alerts' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    trust_executive: [
        { key: 'trust-dashboard', label: 'Trust Dashboard', to: '/trust-dashboard' },
        { key: 'schools', label: 'Schools', to: '/schools' },
        { key: 'alerts', label: 'Alerts', to: '/alerts' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    tenant_admin: [
        { key: 'users', label: 'Users', to: '/users' },
        { key: 'schools', label: 'Schools', to: '/schools' },
        { key: 'import', label: 'Import', to: '/import' },
        { key: 'connectors', label: 'Connectors', to: '/connectors' },
        { key: 'feature-flags', label: 'Feature flags', to: '/feature-flags' },
        { key: 'pilot-toolkit', label: 'Pilot toolkit', to: '/pilot-toolkit' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    platform_operator: [
        { key: 'dashboard', label: 'Dashboard', to: '/' },
        { key: 'pilot-toolkit', label: 'Pilot toolkit', to: '/pilot-toolkit' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
};

/**
 * Teacher / Support bottom nav — visible below md (&lt;768); hidden from md up (DESIGN Hybrid).
 * Labels align with sidebar where the same destination exists (My Pupils).
 */
export const TEACHER_SUPPORT_BOTTOM_NAV = [
    { key: 'home', label: 'Home', to: '/' },
    { key: 'pupils', label: 'My Pupils', to: '/pupils' },
    { key: 'capture', label: 'Capture', to: '/capture' },
    { key: 'drafts', label: 'Drafts', to: '/drafts' },
    { key: 'more', label: 'More', to: '/settings' },
];

/**
 * @param {string|null|undefined} role
 * @returns {string}
 */
function normalizeRole(role) {
    return String(role ?? '').toLowerCase();
}

/**
 * @param {string|null|undefined} role
 * @returns {NavItem[]}
 */
export function navItemsForRole(role) {
    if (!role) {
        return [];
    }

    return NAV_BY_ROLE[normalizeRole(role)] ?? [];
}

/**
 * @param {string|null|undefined} role
 * @returns {boolean}
 */
export function usesTeacherSupportBottomNav(role) {
    const key = normalizeRole(role);

    return key === 'teacher' || key === 'support_staff';
}

/**
 * Labels never include Messages (not in PRD).
 *
 * @param {string|null|undefined} role
 * @returns {string[]}
 */
export function navLabelsForRole(role) {
    return navItemsForRole(role).map((item) => item.label);
}

/**
 * Unique `to` targets from Role sidebars and Teacher/Support bottom nav.
 *
 * @returns {string[]}
 */
export function allNavTargets() {
    const targets = new Set();

    for (const items of Object.values(NAV_BY_ROLE)) {
        for (const item of items) {
            targets.add(item.to);
        }
    }

    for (const item of TEACHER_SUPPORT_BOTTOM_NAV) {
        targets.add(item.to);
    }

    return [...targets];
}
