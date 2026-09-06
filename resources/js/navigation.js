/**
 * Deferred router access for modules that must not import the router graph
 * (avoids client → router → session → auth → client cycles).
 */

/** @type {import('vue-router').Router | null} */
let routerInstance = null;

/**
 * @param {import('vue-router').Router} router
 */
export function setRouter(router) {
    routerInstance = router;
}

/**
 * Navigate to AccessDenied for API `forbidden` responses.
 * Vue menus never authorise APIs — this only reflects 403 JSON.
 *
 * @returns {Promise<void>}
 */
export async function navigateToAccessDenied() {
    if (!routerInstance) {
        return;
    }

    if (routerInstance.currentRoute.value.name === 'access-denied') {
        return;
    }

    await routerInstance.push({ name: 'access-denied' });
}
