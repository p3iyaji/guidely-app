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
});
