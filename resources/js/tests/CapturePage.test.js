/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CapturePage from '../pages/CapturePage.vue';

vi.mock('../api/client.js', () => ({
    apiFetch: vi.fn(),
}));

vi.mock('vue-router', () => ({
    useRoute: () => ({
        name: 'capture',
        meta: {},
    }),
}));

import { apiFetch } from '../api/client.js';

function mockLoadSuccess() {
    apiFetch
        .mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                data: [
                    {
                        id: '01hpupil1',
                        given_name: 'Maya',
                        family_name: 'Okonkwo',
                    },
                ],
            }),
        })
        .mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                data: [
                    {
                        id: '01hsetting1',
                        code: 'CLASSROOM',
                        label: 'Classroom',
                    },
                ],
            }),
        });
}

describe('CapturePage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        document.title = '';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('loads pupils and setting terms into the capture form', async () => {
        mockLoadSuccess();

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(apiFetch).toHaveBeenCalledWith('/api/v1/pupils');
        expect(apiFetch).toHaveBeenCalledWith('/api/v1/ontology/setting-terms');
        expect(wrapper.find('[data-testid="capture-form-card"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-pupil"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="capture-setting"]').text()).toContain('Classroom');
        expect(wrapper.find('[data-testid="capture-submit"]').classes().join(' ')).toContain('min-h-11');
    });

    it('shows load failure alert when bootstrap requests fail', async () => {
        apiFetch
            .mockResolvedValueOnce({ ok: false })
            .mockResolvedValueOnce({ ok: false });

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-error"]').text())
            .toContain('Unable to load Capture form data.');
    });

    it('shows settings-specific load error when setting terms request fails', async () => {
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hpupil1', given_name: 'Maya', family_name: 'Okonkwo' }] }),
            })
            .mockResolvedValueOnce({ ok: false });

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-error"]').text())
            .toContain('Unable to load Setting terms for Capture.');
    });

    it('disables submit and shows empty settings error when no terms are returned', async () => {
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hpupil1', given_name: 'Maya', family_name: 'Okonkwo' }] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [] }),
            });

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-settings-empty"]').text())
            .toContain('No Setting terms are available for Capture.');
        expect(wrapper.find('[data-testid="capture-submit"]').attributes('disabled')).toBeDefined();
    });

    it('treats non-array bootstrap payloads as empty lists', async () => {
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: null }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: { id: 'not-an-array' } }),
            });

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-pupils-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-settings-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-submit"]').attributes('disabled')).toBeDefined();
    });

    it('submits an observation and shows confirmation with the new id', async () => {
        mockLoadSuccess();
        apiFetch.mockResolvedValueOnce({
            ok: true,
            status: 201,
            json: async () => ({
                data: {
                    id: '01hevidence1',
                    lifecycle: 'submitted',
                    type: 'observation',
                    setting: { id: '01hsetting1', code: 'CLASSROOM', label: 'Classroom' },
                },
            }),
        });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await wrapper.find('[data-testid="capture-setting"]').setValue('01hsetting1');
        await wrapper.find('[data-testid="capture-body"]').setValue('Settled after visual timetable.');
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(apiFetch).toHaveBeenLastCalledWith('/api/v1/observations', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({
                'X-Client-Type': 'web',
            }),
        }));

        const body = JSON.parse(apiFetch.mock.calls.at(-1)[1].body);
        expect(body.pupil_id).toBe('01hpupil1');
        expect(body.setting_term_id).toBe('01hsetting1');
        expect(body.body).toBe('Settled after visual timetable.');
        expect(body.client_type).toBe('web');
        expect(body.occurred_at).toMatch(/Z$/);

        expect(wrapper.find('[data-testid="capture-confirmation"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-confirmation-id"]').text()).toContain('01hevidence1');
    });

    it('shows submit error when response is ok but missing data.id', async () => {
        mockLoadSuccess();
        apiFetch.mockResolvedValueOnce({
            ok: true,
            status: 201,
            json: async () => ({ data: {} }),
        });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await wrapper.find('[data-testid="capture-setting"]').setValue('01hsetting1');
        await wrapper.find('[data-testid="capture-body"]').setValue('Settled after visual timetable.');
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-confirmation"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="capture-submit-error"]').text())
            .toContain('Observation was accepted but no record reference was returned.');
    });

    it('surfaces field errors from a 422 response', async () => {
        mockLoadSuccess();
        apiFetch.mockResolvedValueOnce({
            ok: false,
            status: 422,
            json: async () => ({
                message: 'The given data was invalid.',
                errors: {
                    body: ['What was observed is required.'],
                    setting_term_id: ['A Setting Ontology term is required.'],
                },
            }),
        });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-body-error"]').text())
            .toContain('What was observed is required.');
        expect(wrapper.find('[data-testid="capture-setting-error"]').text())
            .toContain('A Setting Ontology term is required.');
    });
});
