/**
 * Resolve Audit client_type for Capture / flush.
 * Hybrid shell markers, token auth, Capacitor native, and offline-origin flush → hybrid.
 * Default browser Web SPA → web.
 */

export const CLIENT_TYPE_STORAGE_KEY = 'guidely.client_type';
export const HYBRID_TOKEN_STORAGE_KEY = 'guidely.hybrid_access_token';

/**
 * @param {{ offlineOrigin?: boolean, storage?: Storage|null, document?: Document|null, window?: Window|null }} [options]
 * @returns {'web'|'hybrid'}
 */
export function resolveClientType(options = {}) {
    if (options.offlineOrigin) {
        return 'hybrid';
    }

    const win = options.window ?? (typeof window !== 'undefined' ? window : null);
    const doc = options.document ?? (typeof document !== 'undefined' ? document : null);
    const storage = options.storage
        ?? (typeof sessionStorage !== 'undefined' ? sessionStorage : null);

    if (win?.Capacitor?.isNativePlatform?.() === true) {
        return 'hybrid';
    }

    const datasetType = doc?.documentElement?.dataset?.guidelyClient;
    if (datasetType === 'hybrid') {
        return 'hybrid';
    }

    if (storage?.getItem(CLIENT_TYPE_STORAGE_KEY) === 'hybrid') {
        return 'hybrid';
    }

    if (storage?.getItem(HYBRID_TOKEN_STORAGE_KEY)) {
        return 'hybrid';
    }

    return 'web';
}

/**
 * Persist an explicit client shell for Hybrid SPA / token clients.
 *
 * @param {'web'|'hybrid'} clientType
 * @param {Storage|null} [storage]
 */
export function setClientType(clientType, storage = null) {
    const store = storage
        ?? (typeof sessionStorage !== 'undefined' ? sessionStorage : null);

    if (!store) {
        return;
    }

    if (clientType === 'hybrid') {
        store.setItem(CLIENT_TYPE_STORAGE_KEY, 'hybrid');

        return;
    }

    store.removeItem(CLIENT_TYPE_STORAGE_KEY);
}
