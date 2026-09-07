/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useSession } from '../features/auth/session.js';
import OutputsPage from '../pages/OutputsPage.vue';

const listedOutputs = [
    {
        id: 'out_1',
        pupil_id: 'pup_1',
        type: 'review_summary',
        type_label: 'Review summary',
        version: 1,
        confirmed_at: '2026-09-07T12:00:00.000000Z',
        pupil: {
            id: 'pup_1',
            given_name: 'Maya',
            family_name: 'Okonkwo',
        },
        confirmer: {
            id: 1,
            name: 'Alex SENCO',
        },
        review_cycle: {
            id: 'rc_1',
            type: 'annual_review',
            due_on: '2026-09-20',
            status: 'open',
        },
        payload: {
            kind: 'review_summary',
            determination_ids: ['det_1'],
            evidence_ids: ['ev_1'],
            gap_ids: [],
        },
    },
];

describe('OutputsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async () => jsonResponse({ data: listedOutputs }));
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
     * @param {{ outputs?: unknown[], status?: number, generateStatus?: number, generateErrors?: Record<string, string[]>, cycleStatus?: number }} [options]
     */
    async function mountPage(role = 'senco', options = {}) {
        const {
            outputs = listedOutputs,
            status = 200,
            generateStatus = 201,
            generateErrors = {},
            cycleStatus = 200,
        } = options;

        fetchMock.mockImplementation(async (url, init = {}) => {
            const href = String(url);
            const method = String(init.method ?? 'GET').toUpperCase();

            if (href.includes('/api/v1/documentation-outputs') && method === 'POST') {
                if (generateStatus >= 400) {
                    return jsonResponse({
                        message: 'Unable to generate output.',
                        errors: generateErrors,
                    }, generateStatus);
                }

                return jsonResponse({ data: listedOutputs[0] }, 201);
            }

            if (href.includes('/api/v1/documentation-outputs')) {
                if (status === 403) {
                    return jsonResponse({ message: 'You don’t have access.', code: 'forbidden' }, 403);
                }

                return jsonResponse({ data: outputs }, status);
            }

            if (href.includes('/review-cycles')) {
                if (cycleStatus >= 400) {
                    return jsonResponse({ message: 'Unable to load Review Cycles.' }, cycleStatus);
                }

                return jsonResponse({
                    data: [
                        {
                            id: 'rc_1',
                            pupil_id: 'pup_1',
                            type: 'annual_review',
                            due_on: '2026-09-20',
                            status: 'open',
                        },
                        {
                            id: 'rc_closed',
                            pupil_id: 'pup_1',
                            type: 'annual_review',
                            due_on: '2025-09-20',
                            status: 'closed',
                        },
                    ],
                });
            }

            if (href.includes('/api/v1/pupils')) {
                return jsonResponse({
                    data: [
                        {
                            id: 'pup_1',
                            given_name: 'Maya',
                            family_name: 'Okonkwo',
                        },
                    ],
                });
            }

            return jsonResponse({});
        });

        useSession().setUser({
            id: 'usr_1',
            name: 'Alex SENCO',
            email: 'alex@example.com',
            role,
            tenant_id: 'ten_1',
        });

        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/outputs', name: 'outputs', component: OutputsPage },
            ],
        });

        await router.push('/outputs');
        await router.isReady();

        const wrapper = mount(OutputsPage, {
            global: {
                plugins: [router],
            },
        });

        await flushPromises();

        return { wrapper };
    }

    it('lists outputs with disclaimer and Generate for SENCO', async () => {
        const { wrapper } = await mountPage('senco');

        expect(wrapper.find('[data-testid="outputs-page"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="outputs-disclaimer"]').text()).toContain(
            'not diagnoses, funding decisions, or statutory determinations',
        );
        expect(wrapper.find('[data-testid="output-type-label"]').text()).toContain('Review summary');
        expect(wrapper.find('[data-testid="output-pupil-name"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="output-review-cycle-label"]').text()).toContain('Annual Review');
        expect(wrapper.find('[data-testid="outputs-generate-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="output-confirmer-name"]').text()).toContain('Alex SENCO');
        expect(wrapper.find('[data-testid="output-disclaimer-ack"]').exists()).toBe(true);
        expect(wrapper.text().toLowerCase()).not.toContain('confidence');
        expect(wrapper.text().toLowerCase()).not.toContain('auto-approved');
    });

    it('hides Generate for School Leader', async () => {
        const { wrapper } = await mountPage('school_leader');

        expect(wrapper.find('[data-testid="outputs-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="outputs-generate-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="output-generate-submit"]').exists()).toBe(false);
    });

    it('blocks Generate without disclaimer acknowledgement', async () => {
        const { wrapper } = await mountPage('senco');

        expect(wrapper.find('[data-testid="output-generate-submit"]').attributes('disabled')).toBeDefined();

        await wrapper.find('[data-testid="output-pupil"]').setValue('pup_1');
        await wrapper.find('[data-testid="outputs-generate-form"] form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="output-confirm-error"]').attributes('role')).toBe('alert');
        expect(wrapper.find('[data-testid="output-confirm-error"]').text()).toContain(
            'Disclaimer acknowledgement is required.',
        );

        const postCalls = fetchMock.mock.calls.filter(([, init]) => {
            return String(init?.method ?? 'GET').toUpperCase() === 'POST';
        });

        expect(postCalls).toHaveLength(0);
    });

    it('loads open and closed Review Cycles after a Pupil is selected', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="output-pupil"]').setValue('pup_1');
        await flushPromises();

        const optionValues = wrapper
            .find('[data-testid="output-review-cycle"]')
            .findAll('option')
            .map((option) => option.attributes('value'));
        const optionText = wrapper.find('[data-testid="output-review-cycle"]').text();

        expect(optionValues).toContain('rc_1');
        expect(optionValues).toContain('rc_closed');
        expect(optionText).toContain('Closed');

        const cycleCall = fetchMock.mock.calls.find(([url]) => {
            return String(url).includes('/api/v1/pupils/pup_1/review-cycles');
        });

        expect(cycleCall).toBeTruthy();
    });

    it('shows an error when Review Cycles fail to load', async () => {
        const { wrapper } = await mountPage('senco', { cycleStatus: 500 });

        await wrapper.find('[data-testid="output-pupil"]').setValue('pup_1');
        await flushPromises();

        expect(wrapper.find('[data-testid="outputs-cycles-error"]').attributes('role')).toBe('alert');
        expect(wrapper.find('[data-testid="outputs-cycles-error"]').text()).toContain(
            'Unable to load Review Cycles.',
        );
    });

    it('posts confirmation payload then reloads the list', async () => {
        const { wrapper } = await mountPage('senco');

        await wrapper.find('[data-testid="output-pupil"]').setValue('pup_1');
        await flushPromises();
        await wrapper.find('[data-testid="output-review-cycle"]').setValue('rc_1');
        await wrapper.find('[data-testid="output-disclaimer-ack"]').setValue(true);
        await wrapper.find('[data-testid="outputs-generate-form"] form').trigger('submit');
        await flushPromises();

        const createCall = fetchMock.mock.calls.find(([url, init]) => {
            return String(url).includes('/api/v1/documentation-outputs')
                && String(init?.method ?? 'GET').toUpperCase() === 'POST';
        });

        expect(createCall).toBeTruthy();
        expect(JSON.parse(createCall[1].body)).toEqual({
            pupil_id: 'pup_1',
            review_cycle_id: 'rc_1',
            type: 'review_summary',
            confirmer_user_id: 'usr_1',
            disclaimer_acknowledged: true,
        });

        const urls = fetchMock.mock.calls.map((call) => String(call[0]));
        expect(urls.filter((url) => url.includes('/api/v1/documentation-outputs')).length).toBeGreaterThan(1);
    });

    it('shows confirm field errors from a 422 response', async () => {
        const { wrapper } = await mountPage('senco', {
            generateStatus: 422,
            generateErrors: {
                confirmer_user_id: ['The confirmer must be the signed-in User.'],
            },
        });

        await wrapper.find('[data-testid="output-disclaimer-ack"]').setValue(true);
        await wrapper.find('[data-testid="outputs-generate-form"] form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="output-confirm-error"]').attributes('role')).toBe('alert');
        expect(wrapper.find('[data-testid="output-confirm-error"]').text()).toContain(
            'The confirmer must be the signed-in User.',
        );
    });

    it('shows access error for forbidden Outputs responses', async () => {
        const { wrapper } = await mountPage('teacher', { status: 403 });

        expect(wrapper.find('[data-testid="outputs-error"]').text()).toContain('You don’t have access.');
        expect(wrapper.find('[data-testid="outputs-generate-form"]').exists()).toBe(false);
    });
});

/**
 * @param {unknown} body
 * @param {number} [status]
 */
function jsonResponse(body, status = 200) {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
