/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    CLIENT_TYPE_STORAGE_KEY,
    resolveClientType,
    setClientType,
} from '../features/evidence/clientType.js';
import {
    OFFLINE_DRAFT_BANNER,
    PUPIL_IDENTITY_CONFLICT_MESSAGE,
} from '../features/evidence/offlineBanner.js';
import {
    clearOfflineDraftQueue,
    enqueueOfflineDraft,
    isLikelyOffline,
    listOfflineDrafts,
    LOCAL_STORAGE_QUEUE_KEY,
    removeOfflineDraft,
} from '../features/evidence/offlineDraftQueue.js';
import {
    flushOfflineDrafts,
    isPupilIdentityConflict,
} from '../features/evidence/flushOfflineDrafts.js';

vi.mock('../api/client.js', () => ({
    apiFetch: vi.fn(),
}));

import { apiFetch } from '../api/client.js';

describe('offlineBanner copy', () => {
    it('uses the exact not-on-server banner', () => {
        expect(OFFLINE_DRAFT_BANNER).toBe(
            'Saved on this device — not on the Evidence Base until you reconnect',
        );
    });
});

describe('resolveClientType', () => {
    beforeEach(() => {
        sessionStorage.clear();
        localStorage.clear();
        delete document.documentElement.dataset.guidelyClient;
    });

    it('defaults to web', () => {
        expect(resolveClientType()).toBe('web');
    });

    it('returns hybrid for offline-origin flush', () => {
        expect(resolveClientType({ offlineOrigin: true })).toBe('hybrid');
    });

    it('returns hybrid when shell marker or token is present', () => {
        setClientType('hybrid');
        expect(resolveClientType()).toBe('hybrid');

        sessionStorage.removeItem(CLIENT_TYPE_STORAGE_KEY);
        sessionStorage.setItem('guidely.hybrid_access_token', 'token');
        expect(resolveClientType()).toBe('hybrid');

        sessionStorage.clear();
        document.documentElement.dataset.guidelyClient = 'hybrid';
        expect(resolveClientType()).toBe('hybrid');
    });
});

describe('offlineDraftQueue', () => {
    beforeEach(async () => {
        await clearOfflineDraftQueue();
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            get: () => true,
        });
    });

    afterEach(async () => {
        await clearOfflineDraftQueue();
    });

    it('enqueues FIFO and lists local-only items', async () => {
        const first = await enqueueOfflineDraft({
            type: 'observation',
            payload: { pupil_id: '01hpupil1', occurred_at: '2026-09-06T10:00:00.000Z' },
            pupilLabel: 'Maya Okonkwo',
            createdAt: 1,
        });
        const second = await enqueueOfflineDraft({
            type: 'intervention',
            payload: { pupil_id: '01hpupil1', occurred_at: '2026-09-06T11:00:00.000Z' },
            createdAt: 2,
        });

        const listed = await listOfflineDrafts();
        expect(listed.map((row) => row.id)).toEqual([first.id, second.id]);
        expect(listed[0].pupilLabel).toBe('Maya Okonkwo');
        expect(listed.every((row) => !row.serverAccepted)).toBe(true);
    });

    it('falls back to localStorage when IndexedDB is unavailable', async () => {
        const original = globalThis.indexedDB;
        // @ts-expect-error force fallback path
        delete globalThis.indexedDB;
        await clearOfflineDraftQueue();

        await enqueueOfflineDraft({
            id: 'local_fallback',
            type: 'response',
            payload: { pupil_id: '01hpupil1', body: 'ok' },
        });

        const raw = localStorage.getItem(LOCAL_STORAGE_QUEUE_KEY);
        expect(raw).toContain('local_fallback');
        expect(await listOfflineDrafts()).toHaveLength(1);

        globalThis.indexedDB = original;
    });

    it('detects offline via navigator.onLine', () => {
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            get: () => false,
        });
        expect(isLikelyOffline()).toBe(true);
    });
});

describe('flushOfflineDrafts', () => {
    beforeEach(async () => {
        vi.clearAllMocks();
        await clearOfflineDraftQueue();
    });

    afterEach(async () => {
        await clearOfflineDraftQueue();
    });

    it('flushes FIFO through draft create API as hybrid and removes successes', async () => {
        await enqueueOfflineDraft({
            id: 'local_1',
            type: 'observation',
            createdAt: 1,
            payload: {
                pupil_id: '01hpupil1',
                occurred_at: '2026-09-06T10:00:00.000Z',
                body: 'Notes',
            },
        });
        await enqueueOfflineDraft({
            id: 'local_2',
            type: 'intervention',
            createdAt: 2,
            payload: {
                pupil_id: '01hpupil1',
                occurred_at: '2026-09-06T11:00:00.000Z',
                provision_term_id: '01hprovision1',
            },
        });

        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                status: 201,
                json: async () => ({ data: { id: '01hdraft1' } }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 201,
                json: async () => ({ data: { id: '01hdraft2' } }),
            });

        const result = await flushOfflineDrafts();
        expect(result.flushed).toBe(2);
        expect(result.retained).toBe(0);
        expect(await listOfflineDrafts()).toHaveLength(0);

        expect(apiFetch.mock.calls[0][0]).toBe('/api/v1/observations');
        expect(apiFetch.mock.calls[0][1].headers['X-Client-Type']).toBe('hybrid');
        const firstBody = JSON.parse(apiFetch.mock.calls[0][1].body);
        expect(firstBody.client_type).toBe('hybrid');
        expect(firstBody.lifecycle).toBe('draft');

        expect(apiFetch.mock.calls[1][0]).toBe('/api/v1/interventions');
    });

    it('keeps local item and surfaces field errors on 422 validation', async () => {
        await enqueueOfflineDraft({
            id: 'local_bad',
            type: 'observation',
            payload: {
                pupil_id: '01hpupil1',
                occurred_at: '2099-01-01T00:00:00.000Z',
            },
        });

        apiFetch.mockResolvedValueOnce({
            ok: false,
            status: 422,
            json: async () => ({
                message: 'Please correct the highlighted fields.',
                errors: { occurred_at: ['Session date and time cannot be in the future.'] },
            }),
        });

        const result = await flushOfflineDrafts();
        expect(result.flushed).toBe(0);
        expect(result.retained).toBe(1);

        const retained = await listOfflineDrafts();
        expect(retained).toHaveLength(1);
        expect(retained[0].status).toBe('flush_error');
        expect(retained[0].fieldErrors.occurred_at[0]).toContain('cannot be in the future');
    });

    it('retains local draft with conflict messaging when Pupil identity fails', async () => {
        await enqueueOfflineDraft({
            id: 'local_conflict',
            type: 'observation',
            payload: {
                pupil_id: '01hgone',
                occurred_at: '2026-09-06T10:00:00.000Z',
            },
        });

        apiFetch.mockResolvedValueOnce({
            ok: false,
            status: 422,
            json: async () => ({
                message: 'The given data was invalid.',
                errors: { pupil_id: ['The selected Pupil could not be found.'] },
            }),
        });

        const result = await flushOfflineDrafts();
        expect(result.retained).toBe(1);
        expect(result.results[0].conflict).toBe(true);

        const retained = await listOfflineDrafts();
        expect(retained[0].status).toBe('pupil_conflict');
        expect(retained[0].lastError).toBe(PUPIL_IDENTITY_CONFLICT_MESSAGE);
        expect(isPupilIdentityConflict(422, {
            errors: { pupil_id: ['The selected Pupil could not be found.'] },
        })).toBe(true);
    });

    it('PATCHes existing server draft ids on flush', async () => {
        await enqueueOfflineDraft({
            id: 'local_patch',
            type: 'observation',
            serverDraftId: '01hdraft9',
            payload: {
                pupil_id: '01hpupil1',
                occurred_at: '2026-09-06T10:00:00.000Z',
                body: 'Updated offline',
            },
        });

        apiFetch.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({ data: { id: '01hdraft9' } }),
        });

        await flushOfflineDrafts();
        expect(apiFetch).toHaveBeenCalledWith('/api/v1/drafts/01hdraft9', expect.objectContaining({
            method: 'PATCH',
        }));
        expect(await listOfflineDrafts()).toHaveLength(0);
        await removeOfflineDraft('missing');
    });

    it('marks plain 403/404 as flush_error, not pupil_conflict', async () => {
        expect(isPupilIdentityConflict(403, { message: 'Forbidden', code: 'forbidden' })).toBe(false);
        expect(isPupilIdentityConflict(404, { message: 'Not found' })).toBe(false);
        expect(isPupilIdentityConflict(404, {})).toBe(false);
        expect(isPupilIdentityConflict(403, {
            message: 'You cannot capture for this Pupil.',
        })).toBe(true);

        await enqueueOfflineDraft({
            id: 'local_auth',
            type: 'observation',
            payload: { pupil_id: '01hpupil1', occurred_at: '2026-09-06T10:00:00.000Z' },
        });

        apiFetch.mockResolvedValueOnce({
            ok: false,
            status: 403,
            json: async () => ({ message: 'Forbidden', code: 'forbidden' }),
        });

        const result = await flushOfflineDrafts();
        expect(result.results[0].conflict).toBeUndefined();
        const retained = await listOfflineDrafts();
        expect(retained[0].status).toBe('flush_error');
    });

    it('POSTs response drafts to /api/v1/responses', async () => {
        await enqueueOfflineDraft({
            id: 'local_response',
            type: 'response',
            payload: {
                pupil_id: '01hpupil1',
                occurred_at: '2026-09-06T10:00:00.000Z',
                body: 'Responded well',
            },
        });

        apiFetch.mockResolvedValueOnce({
            ok: true,
            status: 201,
            json: async () => ({ data: { id: '01hresp1' } }),
        });

        await flushOfflineDrafts();
        expect(apiFetch.mock.calls[0][0]).toBe('/api/v1/responses');
    });

    it('stops FIFO flush when a network error is thrown', async () => {
        await enqueueOfflineDraft({
            id: 'local_a',
            type: 'observation',
            createdAt: 1,
            payload: { pupil_id: '01hpupil1', occurred_at: '2026-09-06T10:00:00.000Z' },
        });
        await enqueueOfflineDraft({
            id: 'local_b',
            type: 'observation',
            createdAt: 2,
            payload: { pupil_id: '01hpupil1', occurred_at: '2026-09-06T11:00:00.000Z' },
        });

        apiFetch.mockRejectedValueOnce(new TypeError('Failed to fetch'));

        const result = await flushOfflineDrafts();
        expect(result.flushed).toBe(0);
        expect(result.retained).toBe(1);
        expect(apiFetch).toHaveBeenCalledTimes(1);
        expect(await listOfflineDrafts()).toHaveLength(2);
    });

    it('rejects invalid type and corrupt payload without defaulting to observations', async () => {
        await enqueueOfflineDraft({
            id: 'local_bad_type',
            // @ts-expect-error intentional corrupt type
            type: 'nonsense',
            payload: { pupil_id: '01hpupil1' },
        });
        await enqueueOfflineDraft({
            id: 'local_bad_payload',
            type: 'observation',
            // @ts-expect-error intentional corrupt payload
            payload: null,
        });

        const result = await flushOfflineDrafts();
        expect(apiFetch).not.toHaveBeenCalled();
        expect(result.retained).toBe(2);
        const retained = await listOfflineDrafts();
        expect(retained.every((row) => row.status === 'flush_error')).toBe(true);
    });
});
