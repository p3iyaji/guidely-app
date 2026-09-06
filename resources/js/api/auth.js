/**
 * Thin credentialed API helpers for Sanctum SPA auth.
 */

import { apiFetch } from './client';

function readCookie(name) {
    const encoded = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')
        .slice(1)
        .join('=');

    return encoded ? decodeURIComponent(encoded) : '';
}

export async function ensureCsrfCookie() {
    const response = await fetch('/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'include',
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('Unable to initialise CSRF protection.');
    }
}

/**
 * @param {string} email
 * @param {string} password
 * @returns {Promise<{ ok: true } | { ok: false, message: string, code?: string }>}
 */
export async function login(email, password) {
    await ensureCsrfCookie();

    const response = await fetch('/api/v1/login', {
        method: 'POST',
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ email, password }),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        return {
            ok: false,
            message: payload.message ?? 'These credentials do not match our records.',
            code: payload.code,
        };
    }

    return { ok: true };
}

/**
 * @returns {Promise<void>}
 */
export async function logout() {
    await ensureCsrfCookie();

    const response = await fetch('/api/v1/logout', {
        method: 'POST',
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error('Unable to sign out. Please try again.');
    }
}

/**
 * Session bootstrap for the authenticated app shell.
 *
 * @returns {Promise<{ ok: true, user: object } | { ok: false, status: number }>}
 */
export async function fetchMe() {
    const response = await apiFetch('/api/v1/me');

    if (response.status === 401) {
        return { ok: false, status: 401 };
    }

    if (!response.ok) {
        return { ok: false, status: response.status };
    }

    const payload = await response.json();

    return { ok: true, user: payload.data };
}
