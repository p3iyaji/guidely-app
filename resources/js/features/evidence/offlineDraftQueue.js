/**
 * Client-local offline draft queue (IndexedDB with localStorage fallback).
 * Never presents queued items as Evidence Base / server drafts.
 */

const DB_NAME = 'guidely-offline';
const DB_VERSION = 1;
const STORE_NAME = 'draft_queue';
export const LOCAL_STORAGE_QUEUE_KEY = 'guidely.offline_draft_queue';
export const OFFLINE_QUEUE_CHANGED_EVENT = 'guidely:offline-queue-changed';

/**
 * @typedef {'observation'|'intervention'|'response'} OfflineDraftType
 * @typedef {'queued'|'flush_error'|'pupil_conflict'} OfflineDraftStatus
 *
 * @typedef {object} OfflineDraftItem
 * @property {string} id
 * @property {number} createdAt
 * @property {OfflineDraftType} type
 * @property {Record<string, unknown>} payload
 * @property {string|null} [serverDraftId]
 * @property {string} [pupilLabel]
 * @property {OfflineDraftStatus} [status]
 * @property {string} [lastError]
 * @property {Record<string, string[]>} [fieldErrors]
 */

/** @type {Promise<IDBDatabase|null>|null} */
let dbOpenPromise = null;
/** @type {boolean|null} */
let idbAvailable = null;

export class OfflineQueueFullError extends Error {
    /**
     * @param {string} [message]
     */
    constructor(message = 'Offline draft queue is full on this device.') {
        super(message);
        this.name = 'OfflineQueueFullError';
        this.code = 'queue_full';
    }
}

/**
 * @param {object} [detail]
 */
export function emitOfflineQueueChanged(detail = {}) {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(OFFLINE_QUEUE_CHANGED_EVENT, { detail }));
}

/**
 * @returns {string}
 */
export function createLocalDraftId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `local_${crypto.randomUUID()}`;
    }

    return `local_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * @returns {boolean}
 */
export function isLikelyOffline() {
    return typeof navigator !== 'undefined' && navigator.onLine === false;
}

/**
 * @param {unknown} error
 * @returns {boolean}
 */
export function isNetworkError(error) {
    if (!error || typeof error !== 'object') {
        return false;
    }

    const name = /** @type {{ name?: string }} */ (error).name ?? '';
    const message = String(/** @type {{ message?: string }} */ (error).message ?? '');

    return error instanceof TypeError
        || name === 'TypeError'
        || name === 'NetworkError'
        || message === 'Failed to fetch'
        || message.includes('Failed to fetch')
        || message.includes('NetworkError');
}

/**
 * @returns {Promise<IDBDatabase|null>}
 */
function openDb() {
    if (idbAvailable === false) {
        return Promise.resolve(null);
    }

    if (typeof indexedDB === 'undefined') {
        idbAvailable = false;

        return Promise.resolve(null);
    }

    if (!dbOpenPromise) {
        dbOpenPromise = new Promise((resolve) => {
            let request;

            try {
                request = indexedDB.open(DB_NAME, DB_VERSION);
            } catch {
                idbAvailable = false;
                dbOpenPromise = null;
                resolve(null);

                return;
            }

            request.onerror = () => {
                idbAvailable = false;
                dbOpenPromise = null;
                resolve(null);
            };

            request.onupgradeneeded = () => {
                const db = request.result;

                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, { keyPath: 'id' });
                    store.createIndex('createdAt', 'createdAt', { unique: false });
                }
            };

            request.onsuccess = () => {
                idbAvailable = true;
                resolve(request.result);
            };
        });
    }

    return dbOpenPromise;
}

/**
 * @returns {OfflineDraftItem[]}
 */
function readLocalStorageQueue() {
    if (typeof localStorage === 'undefined') {
        return [];
    }

    try {
        const raw = localStorage.getItem(LOCAL_STORAGE_QUEUE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

/**
 * @param {OfflineDraftItem[]} items
 */
function writeLocalStorageQueue(items) {
    if (typeof localStorage === 'undefined') {
        return;
    }

    try {
        localStorage.setItem(LOCAL_STORAGE_QUEUE_KEY, JSON.stringify(items));
    } catch (error) {
        const name = /** @type {{ name?: string, code?: number }} */ (error)?.name;
        const code = /** @type {{ code?: number }} */ (error)?.code;

        if (name === 'QuotaExceededError' || name === 'NS_ERROR_DOM_QUOTA_REACHED' || code === 22) {
            throw new OfflineQueueFullError();
        }

        throw error;
    }
}

/**
 * @param {IDBDatabase} db
 * @returns {Promise<OfflineDraftItem[]>}
 */
function idbList(db) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const store = tx.objectStore(STORE_NAME);
        const request = store.getAll();

        request.onsuccess = () => {
            const rows = Array.isArray(request.result) ? request.result : [];
            resolve(rows);
        };
        request.onerror = () => reject(request.error);
    });
}

/**
 * @param {IDBDatabase} db
 * @param {OfflineDraftItem} item
 * @returns {Promise<void>}
 */
function idbPut(db, item) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).put(item);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

/**
 * @param {IDBDatabase} db
 * @param {string} id
 * @returns {Promise<void>}
 */
function idbDelete(db, id) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

/**
 * @param {Map<string, OfflineDraftItem>} byId
 * @param {OfflineDraftItem[]} rows
 */
function mergeRows(byId, rows) {
    for (const row of rows) {
        if (!row || typeof row !== 'object' || !row.id) {
            continue;
        }

        const existing = byId.get(row.id);

        if (!existing || (row.createdAt ?? 0) >= (existing.createdAt ?? 0)) {
            byId.set(row.id, row);
        }
    }
}

/**
 * @returns {Promise<OfflineDraftItem[]>}
 */
export async function listOfflineDrafts() {
    /** @type {Map<string, OfflineDraftItem>} */
    const byId = new Map();

    mergeRows(byId, readLocalStorageQueue());

    const db = await openDb();

    if (db) {
        try {
            mergeRows(byId, await idbList(db));
        } catch {
            // Keep localStorage rows already merged.
        }
    }

    return [...byId.values()].sort((a, b) => (a.createdAt ?? 0) - (b.createdAt ?? 0));
}

/**
 * @param {Omit<OfflineDraftItem, 'id'|'createdAt'|'status'> & { id?: string, createdAt?: number, status?: OfflineDraftStatus }} input
 * @returns {Promise<OfflineDraftItem>}
 */
export async function enqueueOfflineDraft(input) {
    const existingId = input.id;
    let createdAt = input.createdAt ?? Date.now();

    if (existingId) {
        const existing = (await listOfflineDrafts()).find((row) => row.id === existingId);

        if (existing?.createdAt) {
            createdAt = existing.createdAt;
        }
    }

    /** @type {OfflineDraftItem} */
    const item = {
        id: existingId ?? createLocalDraftId(),
        createdAt,
        type: input.type,
        payload: input.payload,
        serverDraftId: input.serverDraftId ?? null,
        pupilLabel: input.pupilLabel,
        status: input.status ?? 'queued',
        lastError: input.lastError,
        fieldErrors: input.fieldErrors,
    };

    const db = await openDb();

    if (db) {
        try {
            await idbPut(db, item);
            emitOfflineQueueChanged({ action: 'enqueue', id: item.id });

            return item;
        } catch {
            // Fall through to localStorage.
        }
    }

    const queue = readLocalStorageQueue().filter((row) => row.id !== item.id);
    queue.push(item);
    writeLocalStorageQueue(queue);
    emitOfflineQueueChanged({ action: 'enqueue', id: item.id });

    return item;
}

/**
 * @param {string} id
 * @param {Partial<OfflineDraftItem>} patch
 * @returns {Promise<OfflineDraftItem|null>}
 */
export async function updateOfflineDraft(id, patch) {
    const items = await listOfflineDrafts();
    const existing = items.find((row) => row.id === id);

    if (!existing) {
        return null;
    }

    const next = { ...existing, ...patch, id: existing.id };
    const db = await openDb();

    if (db) {
        try {
            await idbPut(db, next);
            emitOfflineQueueChanged({ action: 'update', id });

            return next;
        } catch {
            // Fall through.
        }
    }

    writeLocalStorageQueue(items.map((row) => (row.id === id ? next : row)));
    emitOfflineQueueChanged({ action: 'update', id });

    return next;
}

/**
 * @param {string} id
 * @returns {Promise<void>}
 */
export async function removeOfflineDraft(id) {
    const db = await openDb();

    if (db) {
        try {
            await idbDelete(db, id);
        } catch {
            // Still clear localStorage copy.
        }
    }

    try {
        writeLocalStorageQueue(readLocalStorageQueue().filter((row) => row.id !== id));
    } catch {
        // Ignore quota errors on shrink.
    }

    emitOfflineQueueChanged({ action: 'remove', id });
}

/**
 * Test helper — clears both stores.
 *
 * @returns {Promise<void>}
 */
export async function clearOfflineDraftQueue() {
    const open = dbOpenPromise;
    dbOpenPromise = null;
    idbAvailable = null;

    if (typeof localStorage !== 'undefined') {
        localStorage.removeItem(LOCAL_STORAGE_QUEUE_KEY);
    }

    if (open) {
        try {
            const db = await open;
            db?.close();
        } catch {
            // Ignore close failures during reset.
        }
    }

    if (typeof indexedDB === 'undefined') {
        return;
    }

    await new Promise((resolve) => {
        const request = indexedDB.deleteDatabase(DB_NAME);
        request.onsuccess = () => resolve();
        request.onerror = () => resolve();
        request.onblocked = () => resolve();
    });
}
