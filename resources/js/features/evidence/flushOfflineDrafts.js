/**
 * Flush offline draft queue FIFO through existing draft create/update APIs.
 * Server FormRequests remain the only business rules. Offline-origin → hybrid.
 */

import { apiFetch } from '../../api/client';
import { resolveClientType } from './clientType';
import { PUPIL_IDENTITY_CONFLICT_MESSAGE } from './offlineBanner';
import {
    emitOfflineQueueChanged,
    listOfflineDrafts,
    removeOfflineDraft,
    updateOfflineDraft,
} from './offlineDraftQueue';

/** @type {Promise<{ flushed: number, retained: number, results: object[] }>|null} */
let flushInFlight = null;

const VALID_TYPES = new Set(['observation', 'intervention', 'response']);

/**
 * @param {'observation'|'intervention'|'response'} type
 * @returns {string}
 */
function createEndpointForType(type) {
    if (type === 'intervention') {
        return '/api/v1/interventions';
    }

    if (type === 'response') {
        return '/api/v1/responses';
    }

    if (type === 'observation') {
        return '/api/v1/observations';
    }

    throw new Error(`Invalid offline draft type: ${type}`);
}

/**
 * @param {Record<string, unknown>} payload
 * @returns {Record<string, unknown>}
 */
function withHybridClientType(payload) {
    return {
        ...payload,
        client_type: resolveClientType({ offlineOrigin: true }),
    };
}

/**
 * @param {number} status
 * @param {{ message?: string, code?: string, errors?: Record<string, string[]> }} payload
 * @returns {boolean}
 */
export function isPupilIdentityConflict(status, payload = {}) {
    const pupilErrors = payload.errors?.pupil_id ?? [];
    const hasPupilFieldErrors = pupilErrors.length > 0;
    const message = String(payload.message ?? '').toLowerCase();
    const code = String(payload.code ?? '').toLowerCase();
    const pupilIndicated = hasPupilFieldErrors
        || message.includes('pupil')
        || code.includes('pupil');

    if (status === 422) {
        return hasPupilFieldErrors;
    }

    if (status === 403 || status === 404) {
        return pupilIndicated;
    }

    return false;
}

/**
 * @param {import('./offlineDraftQueue').OfflineDraftItem} item
 * @param {string} lastError
 * @returns {Promise<{ ok: false, status: number, payload?: object }>}
 */
async function retainFlushError(item, lastError, payload = {}) {
    await updateOfflineDraft(item.id, {
        status: 'flush_error',
        lastError,
        fieldErrors: payload.errors ?? {},
    });

    return { ok: false, status: 0, payload };
}

/**
 * @param {import('./offlineDraftQueue').OfflineDraftItem} item
 * @returns {Promise<{ ok: boolean, conflict?: boolean, status?: number, payload?: object }>}
 */
async function flushOne(item) {
    if (!VALID_TYPES.has(item.type)) {
        return retainFlushError(
            item,
            'This device draft has an invalid type and cannot be synced.',
        );
    }

    if (!item.payload || typeof item.payload !== 'object' || Array.isArray(item.payload)) {
        return retainFlushError(
            item,
            'This device draft is corrupt and cannot be synced.',
        );
    }

    const body = withHybridClientType(item.payload);
    const headers = {
        'Content-Type': 'application/json',
        'X-Client-Type': 'hybrid',
    };

    const response = item.serverDraftId
        ? await apiFetch(`/api/v1/drafts/${item.serverDraftId}`, {
            method: 'PATCH',
            headers,
            body: JSON.stringify(body),
            skipForbiddenRedirect: true,
        })
        : await apiFetch(createEndpointForType(item.type), {
            method: 'POST',
            headers,
            body: JSON.stringify({
                ...body,
                lifecycle: 'draft',
            }),
            skipForbiddenRedirect: true,
        });

    const payload = await response.json().catch(() => ({}));

    if (response.ok && payload.data?.id) {
        await removeOfflineDraft(item.id);

        return { ok: true, status: response.status, payload };
    }

    if (isPupilIdentityConflict(response.status, payload)) {
        await updateOfflineDraft(item.id, {
            status: 'pupil_conflict',
            lastError: PUPIL_IDENTITY_CONFLICT_MESSAGE,
            fieldErrors: payload.errors ?? {},
        });

        return { ok: false, conflict: true, status: response.status, payload };
    }

    await updateOfflineDraft(item.id, {
        status: 'flush_error',
        lastError: payload.message
            ?? (response.status === 422
                ? 'Please correct the highlighted fields.'
                : 'Unable to sync this draft.'),
        fieldErrors: payload.errors ?? {},
    });

    return { ok: false, status: response.status, payload };
}

/**
 * Flush queued drafts in createdAt FIFO order.
 *
 * @returns {Promise<{ flushed: number, retained: number, results: object[] }>}
 */
export async function flushOfflineDrafts() {
    if (flushInFlight) {
        return flushInFlight;
    }

    flushInFlight = (async () => {
        const items = await listOfflineDrafts();
        const results = [];
        let flushed = 0;
        let retained = 0;

        for (const item of items) {
            try {
                const result = await flushOne(item);
                results.push({ id: item.id, ...result });

                if (result.ok) {
                    flushed += 1;
                } else {
                    retained += 1;
                }
            } catch (error) {
                retained += 1;
                await updateOfflineDraft(item.id, {
                    status: 'flush_error',
                    lastError: 'Unable to sync this draft. It remains on this device.',
                });
                results.push({ id: item.id, ok: false, error });
                // Stop FIFO on network failure so later items wait for reconnect.
                break;
            }
        }

        const summary = { flushed, retained, results };
        emitOfflineQueueChanged({ action: 'flush', ...summary });

        return summary;
    })();

    try {
        return await flushInFlight;
    } finally {
        flushInFlight = null;
    }
}
