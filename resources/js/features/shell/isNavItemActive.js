/**
 * Shared active-state helper for Sidebar and BottomNav.
 *
 * @param {{ path: string }} route
 * @param {string|null|undefined} to
 * @returns {boolean}
 */
export function isNavItemActive(route, to) {
    if (!to) {
        return false;
    }

    if (to === '/') {
        return route.path === '/';
    }

    return route.path === to || route.path.startsWith(`${to}/`);
}
