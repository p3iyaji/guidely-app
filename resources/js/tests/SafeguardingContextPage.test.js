/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SafeguardingContextPage from '../pages/SafeguardingContextPage.vue';

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

describe('SafeguardingContextPage', () => {
    beforeEach(() => {
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

    it('shows FeatureFlaggedEmpty when safeguarding ingest is not available', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({
                message: 'This feature is not available for this Tenant.',
                code: 'feature_not_available',
                feature: 'safeguarding_ingest',
            }, 403)),
        );

        const wrapper = mount(SafeguardingContextPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="safeguarding-context-page"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Not available for this Tenant');
        expect(wrapper.text()).not.toMatch(/Coming soon/i);
        expect(wrapper.text()).not.toMatch(/casework/i);
        expect(wrapper.text()).not.toMatch(/referral/i);
        expect(wrapper.text()).not.toMatch(/LADO/i);
    });

    it('shows empty copy and context labelling without casework language', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({ data: [] })),
        );

        const wrapper = mount(SafeguardingContextPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Safeguarding context');
        expect(wrapper.text()).toContain('not a safeguarding or child-protection case system');
        expect(wrapper.text()).toContain('No safeguarding context signals.');
        expect(wrapper.find('[data-testid="safeguarding-context-disclaimer"]').text()).toContain('not diagnoses');
        expect(wrapper.text()).not.toMatch(/casework/i);
        expect(wrapper.text()).not.toMatch(/AI found/i);
    });

    it('lists present and not-present categories without casework copy', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({
                data: [
                    {
                        id: 'sig_1',
                        pupil_id: 'pup_1',
                        given_name: 'Maya',
                        family_name: 'Okonkwo',
                        present: true,
                        severity: 'medium',
                    },
                    {
                        id: 'sig_2',
                        pupil_id: 'pup_2',
                        given_name: 'Sam',
                        family_name: 'Patel',
                        present: false,
                        severity: null,
                    },
                ],
            })),
        );

        const wrapper = mount(SafeguardingContextPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="safeguarding-context-list"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Maya Okonkwo');
        expect(wrapper.text()).toContain('Present · Medium');
        expect(wrapper.text()).toContain('Not present');
        expect(wrapper.text()).not.toMatch(/casework/i);
    });

    it('shows access copy when the API forbids the Role', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({
                message: 'You don’t have access.',
                code: 'forbidden',
            }, 403)),
        );

        const wrapper = mount(SafeguardingContextPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="safeguarding-context-error"]').text()).toContain('You don’t have access.');
        expect(wrapper.text()).not.toContain('Not available for this Tenant');
    });

    function signal(overrides = {}) {
        return {
            id: 'sig_1',
            pupil_id: 'pup_1',
            given_name: 'Maya',
            family_name: 'Okonkwo',
            present: false,
            severity: null,
            ...overrides,
        };
    }

    function isRecord(value) {
        return value != null && typeof value === 'object' && !Array.isArray(value);
    }

    function fetchMock({ initialData, putResponse, putStatus = 200 }) {
        let getCalls = 0;
        let putCalls = 0;
        const mock = vi.fn(async (url, options) => {
            if (options?.method === 'PUT') {
                putCalls += 1;

                return jsonResponse(putResponse, putStatus);
            }

            getCalls += 1;

            // After a successful PUT the page re-fetches and renders the saved state.
            const data = putCalls > 0 && isRecord(putResponse.data) ? [putResponse.data] : initialData;

            return jsonResponse({ data });
        });

        vi.stubGlobal('fetch', mock);

        return { mock, putCall() {
            const call = mock.mock.calls.find(([, options]) => options?.method === 'PUT');

            return call;
        } };
    }

    it('flags a pupil with severity via PUT and refreshes the list', async () => {
        const flagged = signal({ present: true, severity: 'low' });
        const { mock, putCall } = fetchMock({
            initialData: [signal()],
            putResponse: { data: { ...flagged, updated_at: '2026-09-09T13:00:00Z' } },
        });

        const wrapper = mount(SafeguardingContextPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="record-save-pup_1"]').exists()).toBe(false);

        await wrapper.find('[data-testid="record-present-pup_1"]').setChecked(true);
        await wrapper.find('[data-testid="record-severity-select"]').setValue('low');

        const saveButton = wrapper.find('[data-testid="record-save-pup_1"]');
        expect(saveButton.exists()).toBe(true);
        await wrapper.find('[data-testid="record-form-pup_1"]').trigger('submit.prevent');
        await flushPromises();

        const call = putCall();
        expect(call).toBeTruthy();
        expect(call[0]).toBe('/api/v1/pupils/pup_1/safeguarding-signal');
        expect(JSON.parse(call[1].body)).toEqual({ present: true, severity: 'low' });

        // List refreshed from the API after save.
        const getRequests = mock.mock.calls.filter(([, options]) => !options?.method);
        expect(getRequests.length).toBe(2);
        expect(wrapper.text()).toContain('Present · Low');
    });

    it('sends only present:false when unflagging (no severity key)', async () => {
        const { putCall } = fetchMock({
            initialData: [signal({ present: true, severity: 'high' })],
            putResponse: { data: signal() },
        });

        const wrapper = mount(SafeguardingContextPage);
        await flushPromises();

        // Severity is enabled while present, disabled once unflagged.
        expect(wrapper.find('[data-testid="record-severity-select"]').attributes('disabled')).toBeUndefined();

        await wrapper.find('[data-testid="record-present-pup_1"]').setChecked(false);
        expect(wrapper.find('[data-testid="record-severity-select"]').attributes('disabled')).toBeDefined();

        await wrapper.find('[data-testid="record-form-pup_1"]').trigger('submit.prevent');
        await flushPromises();

        const call = putCall();
        expect(call).toBeTruthy();
        const body = JSON.parse(call[1].body);
        expect(body).toEqual({ present: false });
        expect('severity' in body).toBe(false);
    });

    it('surfaces validation errors inline on 422', async () => {
        fetchMock({
            initialData: [signal()],
            putResponse: {
                message: 'The given data was invalid.',
                errors: { severity: ['Severity must be one of low, medium or high.'] },
            },
            putStatus: 422,
        });

        const wrapper = mount(SafeguardingContextPage);
        await flushPromises();

        await wrapper.find('[data-testid="record-present-pup_1"]').setChecked(true);
        await wrapper.find('[data-testid="record-severity-select"]').setValue('low');
        await wrapper.find('[data-testid="record-form-pup_1"]').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="record-error-pup_1"]').text())
            .toContain('Severity must be one of low, medium or high.');
    });

    it('shows FeatureFlaggedEmpty when the upsert is feature-gated', async () => {
        fetchMock({
            initialData: [signal()],
            putResponse: {
                message: 'This feature is not available for this Tenant.',
                code: 'feature_not_available',
                feature: 'safeguarding_ingest',
            },
            putStatus: 403,
        });

        const wrapper = mount(SafeguardingContextPage);
        await flushPromises();

        await wrapper.find('[data-testid="record-present-pup_1"]').setChecked(true);
        await wrapper.find('[data-testid="record-severity-select"]').setValue('medium');
        await wrapper.find('[data-testid="record-form-pup_1"]').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.text()).toContain('Not available for this Tenant');
    });
});
