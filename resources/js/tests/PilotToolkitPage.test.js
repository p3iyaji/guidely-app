/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import PilotToolkitPage from '../pages/PilotToolkitPage.vue';

describe('PilotToolkitPage', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url, options = {}) => {
                const path = String(url);
                const method = (options.method ?? 'GET').toUpperCase();

                if (path.includes('/api/v1/pilot/disclaimers')) {
                    return {
                        ok: true,
                        status: 200,
                        clone() {
                            return this;
                        },
                        async json() {
                            return {
                                data: {
                                    title: 'Pilot disclaimer pack',
                                    items: [
                                        {
                                            id: 'data-minimisation',
                                            heading: 'Data minimisation',
                                            body: 'Only collect necessary data.',
                                        },
                                    ],
                                },
                            };
                        },
                    };
                }

                if (path.includes('/api/v1/pilot/tenants') && method === 'POST') {
                    return {
                        ok: true,
                        status: 201,
                        clone() {
                            return this;
                        },
                        async json() {
                            return {
                                data: {
                                    id: 'ten_new',
                                    name: 'Pilot School',
                                },
                            };
                        },
                    };
                }

                if (path.includes('/api/v1/pilot/import-template')) {
                    return {
                        ok: true,
                        status: 200,
                        clone() {
                            return this;
                        },
                        async blob() {
                            return new Blob(['pupil_identifier'], { type: 'text/csv' });
                        },
                    };
                }

                if (path.includes('/api/v1/pilot/success-metrics')) {
                    return {
                        ok: true,
                        status: 200,
                        clone() {
                            return this;
                        },
                        async json() {
                            return {
                                data: {
                                    tenant_id: 'ten_1',
                                    metrics: [],
                                },
                            };
                        },
                    };
                }

                return {
                    ok: true,
                    status: 200,
                    clone() {
                        return this;
                    },
                    async json() {
                        return {};
                    },
                    async blob() {
                        return new Blob([''], { type: 'text/plain' });
                    },
                };
            }),
        );

        vi.stubGlobal('URL', {
            createObjectURL: vi.fn(() => 'blob:mock'),
            revokeObjectURL: vi.fn(),
        });

        const originalCreateElement = document.createElement.bind(document);
        vi.spyOn(document, 'createElement').mockImplementation((tagName, options) => {
            const el = originalCreateElement(tagName, options);
            if (String(tagName).toLowerCase() === 'a') {
                el.click = vi.fn();
            }
            return el;
        });

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

    it('shows Tenant Admin toolkit actions and loads disclaimers', async () => {
        useSession().setUser({
            id: 'usr_1',
            name: 'Tenant Admin',
            email: 'admin@example.com',
            role: 'tenant_admin',
            tenant_id: 'ten_1',
        });

        const wrapper = mount(PilotToolkitPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="pilot-toolkit-page"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pilot-tenant-admin-tools"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pilot-create-tenant"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pilot-download-template"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pilot-export-metrics"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pilot-disclaimer-list"]').text()).toContain('Data minimisation');
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
    });

    it('shows Platform Operator create form without Tenant Admin pack', async () => {
        useSession().setUser({
            id: 'usr_op',
            name: 'Operator',
            email: 'ops@example.com',
            role: 'platform_operator',
            tenant_id: null,
        });

        const wrapper = mount(PilotToolkitPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="pilot-create-tenant"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pilot-tenant-admin-tools"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
    });

    it('hides toolkit actions for Teacher', async () => {
        useSession().setUser({
            id: 'usr_t',
            name: 'Teacher',
            email: 'teacher@example.com',
            role: 'teacher',
            tenant_id: 'ten_1',
        });

        const wrapper = mount(PilotToolkitPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="pilot-toolkit-unavailable"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pilot-create-tenant"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pilot-tenant-admin-tools"]').exists()).toBe(false);
    });

    it('posts create tenant on form submit', async () => {
        useSession().setUser({
            id: 'usr_op',
            name: 'Operator',
            email: 'ops@example.com',
            role: 'platform_operator',
            tenant_id: null,
        });

        const wrapper = mount(PilotToolkitPage, {
            attachTo: document.body,
        });
        await flushPromises();

        await wrapper.find('[data-testid="pilot-tenant-name"]').setValue('Pilot School');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            '/api/v1/pilot/tenants',
            expect.objectContaining({
                method: 'POST',
            }),
        );
        expect(wrapper.find('[data-testid="pilot-create-success"]').text()).toContain(
            'guidely:onboard-school',
        );

        wrapper.unmount();
    });

    it('gets import template on download click', async () => {
        useSession().setUser({
            id: 'usr_1',
            name: 'Tenant Admin',
            email: 'admin@example.com',
            role: 'tenant_admin',
            tenant_id: 'ten_1',
        });

        const wrapper = mount(PilotToolkitPage);
        await flushPromises();

        await wrapper.find('[data-testid="pilot-download-template"]').trigger('click');
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            '/api/v1/pilot/import-template',
            expect.any(Object),
        );
    });

    it('gets success metrics on export click', async () => {
        useSession().setUser({
            id: 'usr_1',
            name: 'Tenant Admin',
            email: 'admin@example.com',
            role: 'tenant_admin',
            tenant_id: 'ten_1',
        });

        const wrapper = mount(PilotToolkitPage);
        await flushPromises();

        await wrapper.find('[data-testid="pilot-export-metrics"]').trigger('click');
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            '/api/v1/pilot/success-metrics',
            expect.any(Object),
        );
    });
});
