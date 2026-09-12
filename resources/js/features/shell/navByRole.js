/**
 * Role sidebar / bottom-nav IA from EXPERIENCE.md (Messages omitted).
 * Vue menus are presentation only — they never authorise APIs.
 */

/**
 * @typedef {{ key: string, label: string, to?: string, children?: NavItem[] }} NavItem
 */

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
        { key: 'capture', label: 'Capture', to: '/capture' },
        {
            key: 'reviews',
            label: 'Reviews',
            children: [
                { key: 'review-cycles', label: 'Review Cycles', to: '/review-cycles' },
                { key: 'gaps', label: 'Gaps', to: '/gaps' },
                { key: 'outputs', label: 'Outputs', to: '/outputs' },
            ],
        },
        {
            key: 'oversight',
            label: 'Oversight',
            children: [
                { key: 'school-report', label: 'School Report', to: '/school-report' },
                { key: 'alerts', label: 'Alerts', to: '/alerts' },
                { key: 'safeguarding-context', label: 'Safeguarding context', to: '/safeguarding-context' },
            ],
        },
        { key: 'import', label: 'Import', to: '/import' },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    school_leader: [
        { key: 'dashboard', label: 'Dashboard', to: '/' },
        {
            key: 'oversight',
            label: 'Oversight',
            children: [
                { key: 'school-report', label: 'School Report', to: '/school-report' },
                { key: 'safeguarding-context', label: 'Safeguarding context', to: '/safeguarding-context' },
                { key: 'review-cycles', label: 'Review Cycles', to: '/review-cycles' },
            ],
        },
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
        {
            key: 'access',
            label: 'Administration',
            children: [
                { key: 'users', label: 'Users', to: '/users' },
                { key: 'roles', label: 'Role catalogue', to: '/roles' },
                { key: 'permissions', label: 'Permission catalogue', to: '/permissions' },
                { key: 'audit-events', label: 'Audit events', to: '/audit-events' },
            ],
        },
        {
            key: 'organisation',
            label: 'Organisation',
            children: [
                { key: 'schools', label: 'Schools', to: '/schools' },
                { key: 'pupils', label: 'Pupils', to: '/pupils' },
            ],
        },
        {
            key: 'integrations',
            label: 'Integrations',
            children: [
                { key: 'import', label: 'Import', to: '/import' },
                { key: 'connectors', label: 'Connectors', to: '/connectors' },
            ],
        },
        {
            key: 'configuration',
            label: 'Configuration',
            children: [
                { key: 'feature-flags', label: 'Feature flags', to: '/feature-flags' },
                { key: 'alert-thresholds', label: 'Alert thresholds', to: '/alerts' },
                { key: 'ontology-catalogue', label: 'Ontology catalogue', to: '/ontology-catalogue' },
                { key: 'provision-terms', label: 'Provision terms', to: '/provision-terms' },
                { key: 'need-terms', label: 'Need terms', to: '/need-terms' },
                { key: 'pilot-toolkit', label: 'Pilot toolkit', to: '/pilot-toolkit' },
            ],
        },
        { key: 'settings', label: 'Settings', to: '/settings' },
    ],
    platform_operator: [
        { key: 'dashboard', label: 'Dashboard', to: '/' },
        { key: 'pilot-toolkit', label: 'Pilot toolkit', to: '/pilot-toolkit' },
        { key: 'library-management', label: 'Library management', to: '/library-management' },
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
 * Leaf destinations only — group parents are presentation, not routes.
 *
 * @param {NavItem[]} items
 * @returns {NavItem[]}
 */
export function flattenNavItems(items) {
    const leaves = [];

    for (const item of items ?? []) {
        if (Array.isArray(item.children) && item.children.length > 0) {
            leaves.push(...flattenNavItems(item.children));
            continue;
        }

        leaves.push(item);
    }

    return leaves;
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
 * TopBar search destinations are presentation only. API scope remains the
 * authority for which records each Role can see.
 *
 * @param {string|null|undefined} role
 * @returns {{ key: string, label: string, to: string, placeholder: string }[]}
 */
export function shellSearchScopesForRole(role) {
    const key = normalizeRole(role);
    const pupils = {
        key: 'pupils',
        label: 'Pupils',
        to: '/pupils',
        placeholder: 'Search Pupils',
    };

    if (key === 'teacher' || key === 'support_staff') {
        return [pupils];
    }

    if (key === 'senco' || key === 'school_leader') {
        return [
            pupils,
            {
                key: 'review-cycles',
                label: 'Review Cycles',
                to: '/review-cycles',
                placeholder: 'Search Review Cycles',
            },
        ];
    }

    return [];
}

/**
 * Backwards-compatible helper for consumers that only need Review Cycle
 * availability.
 *
 * @param {string|null|undefined} role
 * @returns {boolean}
 */
export function canSearchReviewCycles(role) {
    return shellSearchScopesForRole(role).some((scope) => scope.key === 'review-cycles');
}

/**
 * Evidence Base is for capture and oversight Roles. Tenant Admin manages the
 * Pupil working record and must not be sent to the Evidence API 403 page.
 * Presentation only — APIs still authorise.
 *
 * @param {string|null|undefined} role
 * @returns {boolean}
 */
export function canViewEvidenceBase(role) {
    const key = normalizeRole(role);

    return key === 'teacher'
        || key === 'support_staff'
        || key === 'senco'
        || key === 'school_leader';
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
 * Labels never include Messages (not in PRD). Group parents are omitted —
 * this is the destination IA, not the sidebar chrome.
 *
 * @param {string|null|undefined} role
 * @returns {string[]}
 */
export function navLabelsForRole(role) {
    return flattenNavItems(navItemsForRole(role)).map((item) => item.label);
}

/**
 * Unique `to` targets from Role sidebars and Teacher/Support bottom nav.
 *
 * @returns {string[]}
 */
export function allNavTargets() {
    const targets = new Set();

    for (const items of Object.values(NAV_BY_ROLE)) {
        for (const item of flattenNavItems(items)) {
            if (item.to) {
                targets.add(item.to);
            }
        }
    }

    for (const item of TEACHER_SUPPORT_BOTTOM_NAV) {
        targets.add(item.to);
    }

    return [...targets];
}
