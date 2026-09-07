/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import { PUPIL_IDENTITY_CONFLICT_MESSAGE } from '../features/evidence/offlineBanner.js';
import {
    clearOfflineDraftQueue,
    enqueueOfflineDraft,
} from '../features/evidence/offlineDraftQueue.js';
import DraftsPage from '../pages/DraftsPage.vue';

vi.mock('../api/client.js', () => ({
    apiFetch: vi.fn(),
}));

vi.mock('../features/evidence/flushOfflineDrafts.js', () => ({
    flushOfflineDrafts: vi.fn(async () => ({ flushed: 0, retained: 0, results: [] })),
}));

const push = vi.fn();

vi.mock('vue-router', () => ({
    useRouter: () => ({
        push,
    }),
    RouterLink: {
        name: 'RouterLink',
        props: ['to'],
        template: '<a><slot /></a>',
    },
}));

import { apiFetch } from '../api/client.js';
import { flushOfflineDrafts } from '../features/evidence/flushOfflineDrafts.js';

describe('DraftsPage', () => {
    beforeEach(async () => {
        vi.clearAllMocks();
        document.title = '';
        await clearOfflineDraftQueue();
        useSession().setUser({
            id: '01hteacher1',
            role: 'teacher',
            given_name: 'Alex',
            family_name: 'Teacher',
        });
    });

    afterEach(async () => {
        useSession().setUser(null);
        await clearOfflineDraftQueue();
        vi.restoreAllMocks();
    });

    it('lists drafts with type, pupil, author, and occurred_at', async () => {
        apiFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                data: [
                    {
                        id: '01hdraft1',
                        type: 'observation',
                        occurred_at: '2026-09-06T10:00:00+00:00',
                        pupil: {
                            id: '01hpupil1',
                            given_name: 'Maya',
                            family_name: 'Okonkwo',
                        },
                        author: {
                            id: '01hteacher1',
                            name: 'Alex Teacher',
                        },
                    },
                    {
                        id: '01hdraft2',
                        type: 'intervention',
                        occurred_at: '2026-09-06T11:00:00+00:00',
                        pupil: null,
                    },
                ],
            }),
        });

        const wrapper = mount(DraftsPage, {
            global: {
                stubs: {
                    RouterLink: {
                        props: ['to'],
                        template: '<a :href="JSON.stringify(to)"><slot /></a>',
                    },
                },
            },
        });
        await flushPromises();

        expect(apiFetch).toHaveBeenCalledWith('/api/v1/drafts');
        expect(wrapper.find('[data-testid="drafts-list"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="draft-row"]')).toHaveLength(1);
        expect(wrapper.find('[data-testid="draft-type"]').text()).toContain('Observation');
        expect(wrapper.find('[data-testid="draft-pupil"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="draft-author"]').text()).toContain('Alex Teacher');
        expect(wrapper.find('[data-testid="draft-occurred-at"]').text()).not.toBe('');
        expect(wrapper.find('[data-testid="draft-row-link-01hdraft1"]').attributes('href'))
            .toContain('draft');
    });

    it('shows empty state with Capture CTA', async () => {
        apiFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ data: [] }),
        });

        const wrapper = mount(DraftsPage, {
            global: {
                stubs: {
                    RouterLink: {
                        props: ['to'],
                        template: '<a href="#"><slot /></a>',
                    },
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="drafts-empty"]').text()).toContain('No drafts yet.');
        await wrapper.find('[data-testid="drafts-capture-cta"]').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'capture' });
    });

    it('surfaces device-local queue with exact banner and conflict retention messaging', async () => {
        await enqueueOfflineDraft({
            id: 'local_device_1',
            type: 'observation',
            pupilLabel: 'Maya Okonkwo',
            payload: { pupil_id: '01hpupil1' },
            status: 'pupil_conflict',
            lastError: PUPIL_IDENTITY_CONFLICT_MESSAGE,
        });

        apiFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ data: [] }),
        });

        const wrapper = mount(DraftsPage, {
            global: {
                stubs: {
                    RouterLink: {
                        props: ['to'],
                        template: '<a href="#"><slot /></a>',
                    },
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="drafts-empty"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="drafts-offline-banner"]').text()).toBe(
            'Saved on this device — not on the Evidence Base until you reconnect',
        );
        expect(wrapper.find('[data-testid="drafts-local-type"]').text()).toContain('Observation');
        expect(wrapper.find('[data-testid="drafts-local-pupil"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="drafts-local-conflict"]').text())
            .toBe(PUPIL_IDENTITY_CONFLICT_MESSAGE);
    });

    it('shows local queue even when server drafts load fails', async () => {
        await enqueueOfflineDraft({
            id: 'local_when_server_fails',
            type: 'intervention',
            pupilLabel: 'Maya Okonkwo',
            payload: { pupil_id: '01hpupil1' },
        });

        apiFetch.mockResolvedValueOnce({ ok: false, status: 500 });

        const wrapper = mount(DraftsPage, {
            global: {
                stubs: {
                    RouterLink: {
                        props: ['to'],
                        template: '<a href="#"><slot /></a>',
                    },
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="drafts-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="drafts-local-queue"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="drafts-local-type"]').text()).toContain('Intervention');
    });

    it('flushes and reloads lists when the browser comes online', async () => {
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [] }),
            })
            .mockResolvedValue({
                ok: true,
                json: async () => ({
                    data: [{
                        id: '01hdraft1',
                        type: 'observation',
                        occurred_at: '2026-09-06T10:00:00+00:00',
                        pupil: {
                            id: '01hpupil1',
                            given_name: 'Maya',
                            family_name: 'Okonkwo',
                        },
                    }],
                }),
            });

        const wrapper = mount(DraftsPage, {
            global: {
                stubs: {
                    RouterLink: {
                        props: ['to'],
                        template: '<a href="#"><slot /></a>',
                    },
                },
            },
        });
        await flushPromises();

        const draftsCallsBefore = apiFetch.mock.calls.filter(([url]) => url === '/api/v1/drafts').length;
        flushOfflineDrafts.mockClear();

        window.dispatchEvent(new Event('online'));
        await flushPromises();

        expect(flushOfflineDrafts).toHaveBeenCalled();
        expect(apiFetch.mock.calls.filter(([url]) => url === '/api/v1/drafts').length)
            .toBeGreaterThan(draftsCallsBefore);
        expect(wrapper.find('[data-testid="drafts-list"]').exists()).toBe(true);

        wrapper.unmount();
    });
});
