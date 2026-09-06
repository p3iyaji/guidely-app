/**
 * Credentialed JSON fetch helper with forbidden → AccessDenied wiring.
 * Vue nav never authorises APIs — this only reflects API 403 responses.
 */

import { navigateToAccessDenied } from '../navigation';

function readCookie(name) {
    const encoded = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')
        .slice(1)
        .join('=');

    return encoded ? decodeURIComponent(encoded) : '';
}

/**
 * @param {string} url
 * @param {RequestInit} [options]
 * @returns {Promise<Response>}
 */
export async function apiFetch(url, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers ?? {}),
    };

    const method = (options.method ?? 'GET').toUpperCase();

    if (method !== 'GET' && method !== 'HEAD') {
        headers['X-XSRF-TOKEN'] = readCookie('XSRF-TOKEN');
    }

    const response = await fetch(url, {
        ...options,
        credentials: 'include',
        headers,
    });

    if (response.status === 403) {
        const payload = await response.clone().json().catch(() => ({}));

        if (payload.code === 'forbidden' || !payload.code) {
            await navigateToAccessDenied();
        }
    }

    return response;
}
