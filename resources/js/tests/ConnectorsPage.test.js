/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConnectorsPage from '../pages/ConnectorsPage.vue';

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

describe('ConnectorsPage', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url, options = {}) => {
                const path = String(url);
                const method = (options.method ?? 'GET').toUpperCase();

                if (path.includes('/api/v1/connectors/sync') && method === 'POST') {
                    return jsonResponse({
                        message: 'Connector sync queued.',
                    }, 202);
                }

                if (path.includes('/api/v1/connectors') && method === 'PUT') {
                    return jsonResponse({
                        data: {
                            id: 'con_1',
                            type: 'pilot_stub',
                            enabled: true,
                            has_secret: true,
                            field_shares: [
                                {
                                    school_id: 'sch_1',
                                    fields: {
                                        given_name: false,
                                        family_name: false,
                                        mis_key: true,
                                        date_of_birth: false,
                                        year_group: false,
                                        send_status: false,
                                    },
                                },
                            ],
                        },
                    });
                }

                if (path.includes('/api/v1/connectors')) {
                    return jsonResponse({
                        data: {
                            id: null,
                            type: 'pilot_stub',
                            enabled: false,
                            has_secret: false,
                            field_shares: [],
                            last_sync_started_at: null,
                            last_sync_completed_at: null,
                            last_sync_failed_at: null,
                            last_error: null,
                        },
                    });
                }

                if (path.includes('/api/v1/schools')) {
                    return jsonResponse({
                        data: [
                            { id: 'sch_1', name: 'Oak Primary', is_active: true },
                        ],
                    });
                }

                return jsonResponse({});
            }),
        );

        Object.defineProperty(document, 'cookie', {
            writable: true,
            configurable: true,
            value: 'XSRF-TOKEN=test-token',
        });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('shows FeatureFlaggedEmpty when connectors are not available for this Tenant', async () => {
        fetch.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/connectors')) {
                return jsonResponse({
                    message: 'This feature is not available for this Tenant.',
                    code: 'feature_not_available',
                    feature: 'connectors',
                }, 403);
            }

            return jsonResponse({ data: [] });
        });

        const wrapper = mount(ConnectorsPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Not available for this Tenant');
        expect(wrapper.find('[data-testid="connectors-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="connectors-sync-form"]').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.text()).not.toContain('Live MIS sync is not part of this screen.');
    });

    it('renders enable, write-only secret, and per-School field checkboxes', async () => {
        const wrapper = mount(ConnectorsPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="connectors-page"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connectors-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connector-enabled"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connector-secret"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connector-secret"]').element.value).toBe('');
        expect(wrapper.find('[data-testid="field-share-sch_1-mis_key"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="field-share-sch_1-year_group"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connectors-sync-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connector-sync-mis-key"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Oak Primary');
        expect(wrapper.text()).toContain('Run sync');
        expect(wrapper.find('[data-testid="connector-health-state"]').text()).toBe('Never synced');
        expect(wrapper.find('[data-testid="connector-last-completed"]').text()).toBe('Never completed');
        expect(wrapper.find('[data-testid="connector-last-failed"]').text()).toBe('Never failed');
        expect(wrapper.text()).toContain('Pilot stub is the supported type until OQ-4');
        expect(wrapper.text()).not.toMatch(/Wonde|Groupcall|Arbor|SIMS|Bromcom/i);
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.text()).not.toContain('Live MIS sync is not part of this screen.');
    });

    it('puts secret and opt-in fields on save and does not echo the secret', async () => {
        const wrapper = mount(ConnectorsPage, {
            attachTo: document.body,
        });
        await flushPromises();

        await wrapper.find('[data-testid="connector-enabled"]').setValue(true);
        await wrapper.find('[data-testid="connector-secret"]').setValue('pilot-secret');
        await wrapper.find('[data-testid="field-share-sch_1-mis_key"]').setValue(true);
        await wrapper.find('[data-testid="connectors-form"]').trigger('submit.prevent');
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            '/api/v1/connectors',
            expect.objectContaining({
                method: 'PUT',
            }),
        );

        const putCall = fetch.mock.calls.find(([, options]) => options?.method === 'PUT');
        const body = JSON.parse(putCall[1].body);

        expect(body.secret).toBe('pilot-secret');
        expect(body.enabled).toBe(true);
        expect(body.field_shares[0].school_id).toBe('sch_1');
        expect(body.field_shares[0].fields.mis_key).toBe(true);
        expect(body.field_shares[0].fields.year_group).toBe(false);
        expect(wrapper.find('[data-testid="connector-secret"]').element.value).toBe('');
        expect(wrapper.find('[data-testid="connector-has-secret"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="field-share-sch_1-mis_key"]').element.checked).toBe(true);
        expect(wrapper.text()).not.toContain('pilot-secret');

        wrapper.unmount();
    });

    it('shows loading until Connector and School requests resolve', async () => {
        let release;
        const gate = new Promise((resolve) => {
            release = resolve;
        });

        fetch.mockImplementation(async (url) => {
            await gate;
            const path = String(url);

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({
                    data: [{ id: 'sch_1', name: 'Oak Primary', is_active: true }],
                });
            }

            return jsonResponse({
                data: {
                    id: null,
                    type: 'pilot_stub',
                    enabled: false,
                    has_secret: false,
                    field_shares: [],
                },
            });
        });

        const wrapper = mount(ConnectorsPage);

        expect(wrapper.find('[data-testid="connectors-loading"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connectors-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="connectors-sync-form"]').exists()).toBe(false);

        release();
        await flushPromises();

        expect(wrapper.find('[data-testid="connectors-loading"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="connectors-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connectors-sync-form"]').exists()).toBe(true);
    });

    it('hydrates field-share checkboxes from the Connector payload', async () => {
        fetch.mockImplementation(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/connectors')) {
                return jsonResponse({
                    data: {
                        id: 'con_1',
                        type: 'pilot_stub',
                        enabled: true,
                        has_secret: true,
                        field_shares: [
                            {
                                school_id: 'sch_1',
                                fields: {
                                    given_name: false,
                                    family_name: false,
                                    mis_key: true,
                                    date_of_birth: false,
                                    year_group: false,
                                    send_status: false,
                                },
                            },
                        ],
                    },
                });
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({
                    data: [{ id: 'sch_1', name: 'Oak Primary', is_active: true }],
                });
            }

            return jsonResponse({});
        });

        const wrapper = mount(ConnectorsPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="field-share-sch_1-mis_key"]').element.checked).toBe(true);
        expect(wrapper.find('[data-testid="field-share-sch_1-year_group"]').element.checked).toBe(false);
        expect(wrapper.find('[data-testid="connector-enabled"]').element.checked).toBe(true);
        expect(wrapper.find('[data-testid="connector-has-secret"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="connector-secret"]').element.value).toBe('');
    });

    it('posts mis_key and optional evidence to Run sync for a School', async () => {
        const wrapper = mount(ConnectorsPage);
        await flushPromises();

        await wrapper.find('[data-testid="connector-enabled"]').setValue(true);
        await wrapper.find('[data-testid="connector-sync-mis-key"]').setValue('MIS-100');
        await wrapper.find('[data-testid="connector-sync-external-id"]').setValue('ext-1');
        await wrapper.find('[data-testid="connector-sync-provision-code"]').setValue('UNIVERSAL');
        await wrapper.find('[data-testid="connector-sync-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('[data-testid="connectors-sync-form"]').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetch.mock.calls.find(([, options]) => options?.method === 'POST');

        expect(postCall[0]).toBe('/api/v1/connectors/sync');
        const body = JSON.parse(postCall[1].body);
        expect(body.school_id).toBe('sch_1');
        expect(body.pupils[0].mis_key).toBe('MIS-100');
        expect(body.pupils[0].evidence_external_id).toBe('ext-1');
        expect(body.pupils[0].evidence_provision_code).toBe('UNIVERSAL');
        expect(Number.isNaN(Date.parse(body.pupils[0].evidence_occurred_at))).toBe(false);
        expect(body.pupils[0].evidence_occurred_at).toMatch(/^\d{4}-\d{2}-\d{2}T/);
        expect(wrapper.find('[data-testid="connectors-sync-success"]').text()).toContain('Connector sync queued.');
        expect(wrapper.find('[data-testid="connector-last-completed"]').text()).toBe('Never completed');
        expect(wrapper.find('[data-testid="connector-health-state"]').text()).toBe('Never synced');
    });

    it('shows completed and failed health and refreshes it on demand', async () => {
        let connectorReads = 0;
        fetch.mockImplementation(async (url) => {
            const path = String(url);

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({
                    data: [{ id: 'sch_1', name: 'Oak Primary', is_active: true }],
                });
            }

            if (path.includes('/api/v1/connectors')) {
                connectorReads++;

                return jsonResponse({
                    data: {
                        id: 'con_1',
                        type: 'pilot_stub',
                        enabled: true,
                        has_secret: true,
                        field_shares: [],
                        last_sync_started_at: connectorReads === 1
                            ? '2026-09-10T08:00:00+00:00'
                            : '2026-09-10T09:00:00+00:00',
                        last_sync_completed_at: connectorReads === 1
                            ? '2026-09-10T08:01:00+00:00'
                            : '2026-09-10T09:01:00+00:00',
                        last_sync_failed_at: '2026-09-09T07:00:00+00:00',
                        last_error: connectorReads === 1
                            ? 'Completed with warnings: 1 pupil record(s) could not be processed.'
                            : null,
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = mount(ConnectorsPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="connector-health-state"]').text())
            .toBe('Last sync completed with warnings');
        expect(wrapper.find('[data-testid="connector-last-completed"]').text()).not.toBe('Never completed');
        expect(wrapper.find('[data-testid="connector-last-failed"]').text()).not.toBe('Never failed');
        expect(wrapper.find('[data-testid="connector-last-error"]').text()).toContain('Completed with warnings');

        await wrapper.find('[data-testid="connector-health-refresh"]').trigger('click');
        await flushPromises();

        expect(connectorReads).toBe(2);
        expect(wrapper.find('[data-testid="connector-health-state"]').text()).toBe('Last sync completed');
        expect(wrapper.find('[data-testid="connector-last-error"]').text()).toBe('None');
    });

    it('shows the Connector disabled message when Run sync returns 422', async () => {
        fetch.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/connectors/sync') && method === 'POST') {
                return jsonResponse({
                    message: 'The Connector is disabled.',
                    code: 'connector_disabled',
                }, 422);
            }

            if (path.includes('/api/v1/schools')) {
                return jsonResponse({
                    data: [{ id: 'sch_1', name: 'Oak Primary', is_active: true }],
                });
            }

            return jsonResponse({
                data: {
                    id: 'con_1',
                    type: 'pilot_stub',
                    enabled: true,
                    has_secret: false,
                    field_shares: [],
                },
            });
        });

        const wrapper = mount(ConnectorsPage);
        await flushPromises();

        await wrapper.find('[data-testid="connector-sync-mis-key"]').setValue('MIS-100');
        await wrapper.find('[data-testid="connectors-sync-form"]').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="connectors-sync-error"]').text()).toContain('The Connector is disabled.');
        expect(wrapper.find('[data-testid="connectors-sync-success"]').exists()).toBe(false);
    });
});
