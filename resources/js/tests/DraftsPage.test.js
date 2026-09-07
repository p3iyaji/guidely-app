/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import DraftsPage from '../pages/DraftsPage.vue';

vi.mock('../api/client.js', () => ({
    apiFetch: vi.fn(),
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

describe('DraftsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        document.title = '';
        useSession().setUser({
            id: '01hteacher1',
            role: 'teacher',
            given_name: 'Alex',
            family_name: 'Teacher',
        });
    });

    afterEach(() => {
        useSession().setUser(null);
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
});
