/**
 * Reconnect listener — flush offline draft queue when the browser comes online.
 */

import { flushOfflineDrafts } from './flushOfflineDrafts';
import { isLikelyOffline, listOfflineDrafts } from './offlineDraftQueue';

/** @type {(() => void)|null} */
let tearDown = null;

/**
 * @returns {() => void} unsubscribe
 */
export function startOfflineFlushListener() {
    if (tearDown) {
        return tearDown;
    }

    const onOnline = () => {
        void flushOfflineDrafts();
    };

    const boot = async () => {
        if (isLikelyOffline()) {
            return;
        }

        const pending = await listOfflineDrafts();

        if (pending.length > 0) {
            await flushOfflineDrafts();
        }
    };

    window.addEventListener('online', onOnline);
    void boot();

    tearDown = () => {
        window.removeEventListener('online', onOnline);
        tearDown = null;
    };

    return tearDown;
}
