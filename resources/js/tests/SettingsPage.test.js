/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import SettingsPage from '../pages/SettingsPage.vue';

const sampleTenant = {
    id: 'ten_1',
    name: 'Northbridge Academy',
    type: 'school',
    cohort_enabled: true,
    cohort_label: 'Pilot 2026',
};

const sampleSso = {
    sso_enabled: false,
    sso_provider: null,
    sso_entity_id: null,
    sso_client_id: null,
};

describe('SettingsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/tenant' && method === 'GET') {
                return jsonResponse({ data: sampleTenant });
            }

            if (path === '/api/v1/tenant/sso' && method === 'GET') {
                return jsonResponse({ data: sampleSso });
            }

            return jsonResponse({});
        });

        vi.stubGlobal('fetch', fetchMock);

        Object.defineProperty(document, 'cookie', {
            writable: true,
            configurable: true,
            value: 'XSRF-TOKEN=test-token',
        });
    });

    afterEach(() => {
        useSession().setUser(null);
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    /**
     * @param {string} [role]
     * @param {{ flush?: boolean, user?: Record<string, unknown> }} [options]
     */
    async function mountPage(role = 'tenant_admin', options = {}) {
        const { flush = true, user } = options;

        useSession().setUser(
            user ?? {
                id: 'usr_1',
                name: 'Ada Lovelace',
                email: 'ada@example.com',
                role,
                tenant_id: role === 'platform_operator' ? null : 'ten_1',
            },
        );

        const wrapper = mount(SettingsPage, {
            global: {
                stubs: {
                    RouterLink: {
                        props: ['to'],
                        template: '<a :href="typeof to === \'string\' ? to : \'#\'"><slot /></a>',
                    },
                },
            },
        });

        if (flush) {
            await flushPromises();
        }

        return wrapper;
    }

    it('shows account, organisation, and SSO for Tenant Admin without Coming soon', async () => {
        const wrapper = await mountPage('tenant_admin');

        expect(wrapper.find('[data-testid="settings-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Settings');
        expect(wrapper.find('[data-testid="settings-account-name"]').text()).toBe('Ada Lovelace');
        expect(wrapper.find('[data-testid="settings-account-email"]').text()).toBe('ada@example.com');
        expect(wrapper.find('[data-testid="settings-account-role"]').text()).toBe('Tenant Admin');
        expect(wrapper.find('[data-testid="settings-edit-profile"]').attributes('href')).toBe('/profile');
        expect(wrapper.find('[data-testid="settings-tenant-name"]').text()).toBe('Northbridge Academy');
        expect(wrapper.find('[data-testid="settings-tenant-type"]').text()).toBe('School');
        expect(wrapper.find('[data-testid="settings-cohort-status"]').text()).toBe('Enabled');
        expect(wrapper.find('[data-testid="settings-cohort-label"]').text()).toBe('Pilot 2026');
        expect(wrapper.find('[data-testid="settings-sso"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="settings-provision-terms"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="settings-provision-terms-open"]').attributes('href')).toBe(
            '/provision-terms',
        );
        expect(wrapper.find('[data-testid="settings-sso-status"]').text()).toBe('Disabled');
        expect(wrapper.find('[data-testid="settings-cohort-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="settings-sso-form"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);

        const ssoGet = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/tenant/sso' && (options?.method ?? 'GET').toUpperCase() === 'GET',
        );

        expect(ssoGet).toBeTruthy();
    });

    it('hides SSO and edit actions for Teachers', async () => {
        const wrapper = await mountPage('teacher');

        expect(wrapper.find('[data-testid="settings-account-role"]').text()).toBe('Teacher');
        expect(wrapper.find('[data-testid="settings-organisation"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="settings-cohort-edit"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="settings-sso"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="settings-provision-terms"]').exists()).toBe(false);

        const ssoGet = fetchMock.mock.calls.find(([url]) => String(url) === '/api/v1/tenant/sso');

        expect(ssoGet).toBeUndefined();
    });

    it('opens Edit cohort in a dialog and PATCHes tenant', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/tenant' && method === 'GET') {
                return jsonResponse({ data: sampleTenant });
            }

            if (path === '/api/v1/tenant/sso' && method === 'GET') {
                return jsonResponse({ data: sampleSso });
            }

            if (path === '/api/v1/tenant' && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        ...sampleTenant,
                        cohort_enabled: false,
                        cohort_label: 'Autumn cohort',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="settings-cohort-edit"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="settings-cohort-form"]');

        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(wrapper.find('[data-testid="settings-organisation"]').exists()).toBe(true);

        const enabled = wrapper.find('[data-testid="settings-cohort-enabled-input"]');
        expect(enabled.element.checked).toBe(true);
        await enabled.setValue(false);
        await wrapper.find('[data-testid="settings-cohort-label-input"]').setValue('Autumn cohort');
        await wrapper.find('[data-testid="settings-cohort-form"] form').trigger('submit');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/tenant' && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            cohort_enabled: false,
            cohort_label: 'Autumn cohort',
        });
        expect(wrapper.find('[data-testid="settings-cohort-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="settings-cohort-status"]').text()).toBe('Disabled');
        expect(wrapper.find('[data-testid="settings-cohort-label"]').text()).toBe('Autumn cohort');
    });

    it('opens Edit SSO in a dialog and PATCHes SSO stub', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/tenant' && method === 'GET') {
                return jsonResponse({ data: sampleTenant });
            }

            if (path === '/api/v1/tenant/sso' && method === 'GET') {
                return jsonResponse({ data: sampleSso });
            }

            if (path === '/api/v1/tenant/sso' && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        sso_enabled: true,
                        sso_provider: 'oidc-placeholder',
                        sso_entity_id: 'https://idp.example/entity',
                        sso_client_id: 'guidely-client',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="settings-sso-edit"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="settings-sso-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="settings-sso"]').exists()).toBe(true);

        await wrapper.find('[data-testid="settings-sso-enabled-input"]').setValue(true);
        await wrapper.find('[data-testid="settings-sso-provider-input"]').setValue('oidc-placeholder');
        await wrapper.find('[data-testid="settings-sso-entity-input"]').setValue('https://idp.example/entity');
        await wrapper.find('[data-testid="settings-sso-client-input"]').setValue('guidely-client');
        await wrapper.find('[data-testid="settings-sso-form"] form').trigger('submit');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/tenant/sso' && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            sso_enabled: true,
            sso_provider: 'oidc-placeholder',
            sso_entity_id: 'https://idp.example/entity',
            sso_client_id: 'guidely-client',
        });
        expect(wrapper.find('[data-testid="settings-sso-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="settings-sso-status"]').text()).toBe('Enabled');
        expect(wrapper.find('[data-testid="settings-sso-provider"]').text()).toBe('oidc-placeholder');
    });

    it('shows validation errors in the cohort dialog', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/tenant' && method === 'GET') {
                return jsonResponse({ data: sampleTenant });
            }

            if (path === '/api/v1/tenant/sso' && method === 'GET') {
                return jsonResponse({ data: sampleSso });
            }

            if (path === '/api/v1/tenant' && method === 'PATCH') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: { cohort_label: ['The cohort label field must not be greater than 255 characters.'] },
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="settings-cohort-edit"]').trigger('click');
        await wrapper.find('[data-testid="settings-cohort-label-input"]').setValue('x'.repeat(300));
        await wrapper.find('[data-testid="settings-cohort-form"] form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-cohort_label"]').text()).toContain('255');
        expect(wrapper.find('[data-testid="settings-cohort-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="settings-cohort-label"]').text()).toBe('Pilot 2026');
    });

    it('shows a Tenant-not-attached state for Platform Operator', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url) === '/api/v1/tenant') {
                return jsonResponse({}, 404);
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage('platform_operator');

        expect(wrapper.find('[data-testid="settings-no-tenant"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="settings-organisation"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="settings-sso"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="settings-account-role"]').text()).toBe('Platform Operator');
    });

    it('runs Review Cycle automation from Settings for Tenant Admin', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path === '/api/v1/tenant' && method === 'GET') {
                return jsonResponse({ data: sampleTenant });
            }

            if (path === '/api/v1/tenant/sso' && method === 'GET') {
                return jsonResponse({ data: sampleSso });
            }

            if (path === '/api/v1/review-cycles/automation/run' && method === 'POST') {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();

        await wrapper.find('[data-testid="settings-automation-run"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="settings-automation-message"]').text()).toContain(
            'No Review Cycles needed rolling forward.',
        );
    });

    it('shows a load error when the Tenant request fails', async () => {
        fetchMock.mockImplementation(async () => jsonResponse({}, 500));

        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="settings-error"]').text()).toContain('Unable to load Settings.');
        expect(wrapper.find('[data-testid="settings-organisation"]').exists()).toBe(false);
    });
});

/**
 * @param {unknown} body
 * @param {number} [status]
 */
function jsonResponse(body, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        clone() {
            return this;
        },
        async json() {
            return body;
        },
    };
}
