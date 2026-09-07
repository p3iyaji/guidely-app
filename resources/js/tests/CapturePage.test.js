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
        })
        .mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                data: [
                    {
                        id: '01hprovision1',
                        code: 'UNIVERSAL',
                        label: 'Universal classroom strategies',
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
        expect(apiFetch).toHaveBeenCalledWith('/api/v1/ontology/provision-terms');
        expect(wrapper.find('[data-testid="capture-form-card"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-pupil"]').text()).toContain('Maya Okonkwo');
        expect(wrapper.find('[data-testid="capture-setting"]').text()).toContain('Classroom');
        expect(wrapper.find('[data-testid="capture-submit"]').classes().join(' ')).toContain('min-h-11');
    });

    it('shows load failure alert when pupils bootstrap fails', async () => {
        apiFetch
            .mockResolvedValueOnce({ ok: false })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hsetting1', code: 'CLASSROOM', label: 'Classroom' }] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hprovision1', code: 'UNIVERSAL', label: 'Universal classroom strategies' }] }),
            });

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-error"]').text())
            .toContain('Unable to load Capture form data.');
    });

    it('soft-fails setting terms and still shows the Capture form', async () => {
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hpupil1', given_name: 'Maya', family_name: 'Okonkwo' }] }),
            })
            .mockResolvedValueOnce({ ok: false })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hprovision1', code: 'UNIVERSAL', label: 'Universal classroom strategies' }] }),
            });

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-error"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="capture-form-card"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-settings-empty"]').text())
            .toContain('Unable to load Setting terms for Capture.');
        expect(wrapper.find('[data-testid="capture-submit"]').attributes('disabled')).toBeDefined();
    });

    it('soft-fails provision terms and still shows Observation form', async () => {
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hpupil1', given_name: 'Maya', family_name: 'Okonkwo' }] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hsetting1', code: 'CLASSROOM', label: 'Classroom' }] }),
            })
            .mockResolvedValueOnce({ ok: false });

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-error"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="capture-form-card"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-setting"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-submit"]').attributes('disabled')).toBeUndefined();
    });

    it('keeps Observation submit enabled when provisions are empty but disables Intervention', async () => {
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hpupil1', given_name: 'Maya', family_name: 'Okonkwo' }] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hsetting1', code: 'CLASSROOM', label: 'Classroom' }] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [] }),
            });

        const wrapper = mount(CapturePage);
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-submit"]').attributes('disabled')).toBeUndefined();

        await wrapper.find('[data-testid="capture-mode-intervention"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-provisions-empty"]').text())
            .toContain('No Provision terms are available for Capture.');
        expect(wrapper.find('[data-testid="capture-submit"]').attributes('disabled')).toBeDefined();
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
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [{ id: '01hprovision1', code: 'UNIVERSAL', label: 'Universal classroom strategies' }] }),
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
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: { id: 'also-not-an-array' } }),
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
        expect(wrapper.find('[data-testid="capture-confirmation"]').text()).toContain('Observation submitted');
    });

    it('switches to Intervention mode, submits, and shows confirmation with the new id', async () => {
        mockLoadSuccess();
        apiFetch.mockResolvedValueOnce({
            ok: true,
            status: 201,
            json: async () => ({
                data: {
                    id: '01hevidence2',
                    lifecycle: 'submitted',
                    type: 'intervention',
                    provision: { id: '01hprovision1', code: 'UNIVERSAL', label: 'Universal classroom strategies' },
                },
            }),
        });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-mode-intervention"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-provision"]').text())
            .toContain('Universal classroom strategies');
        expect(wrapper.find('[data-testid="capture-setting"]').exists()).toBe(false);

        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await wrapper.find('[data-testid="capture-provision"]').setValue('01hprovision1');
        await wrapper.find('[data-testid="capture-body"]').setValue('Used visual timetable before transition.');
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(apiFetch).toHaveBeenLastCalledWith('/api/v1/interventions', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({
                'X-Client-Type': 'web',
            }),
        }));

        const body = JSON.parse(apiFetch.mock.calls.at(-1)[1].body);
        expect(body.pupil_id).toBe('01hpupil1');
        expect(body.provision_term_id).toBe('01hprovision1');
        expect(body.body).toBe('Used visual timetable before transition.');
        expect(body.client_type).toBe('web');
        expect(body.occurred_at).toMatch(/Z$/);

        expect(wrapper.find('[data-testid="capture-confirmation"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-confirmation-id"]').text()).toContain('01hevidence2');
        expect(wrapper.find('[data-testid="capture-confirmation"]').text()).toContain('Intervention submitted');
        expect(wrapper.find('[data-testid="capture-confirmation-provision"]').text())
            .toContain('Universal classroom strategies');
    });

    it('surfaces provision field errors from a 422 response in Intervention mode', async () => {
        mockLoadSuccess();
        apiFetch.mockResolvedValueOnce({
            ok: false,
            status: 422,
            json: async () => ({
                message: 'The given data was invalid.',
                errors: {
                    provision_term_id: ['A Provision Ontology term is required.'],
                },
            }),
        });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-mode-intervention"]').trigger('click');
        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-provision-error"]').text())
            .toContain('A Provision Ontology term is required.');
    });

    it('shows submit error when Observation response is ok but missing data.id', async () => {
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

    it('shows submit error when Intervention response is ok but missing data.id', async () => {
        mockLoadSuccess();
        apiFetch.mockResolvedValueOnce({
            ok: true,
            status: 201,
            json: async () => ({ data: {} }),
        });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-mode-intervention"]').trigger('click');
        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await wrapper.find('[data-testid="capture-provision"]').setValue('01hprovision1');
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-confirmation"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="capture-submit-error"]').text())
            .toContain('Intervention was accepted but no record reference was returned.');
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

    it('switches to Pupil Response mode, loads interventions, submits, and shows confirmation', async () => {
        mockLoadSuccess();
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    data: [
                        {
                            id: '01hintervention1',
                            type: 'intervention',
                            occurred_at: '2026-09-06T09:00:00Z',
                            provision: { id: '01hprovision1', code: 'UNIVERSAL', label: 'Universal classroom strategies' },
                        },
                    ],
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 201,
                json: async () => ({
                    data: {
                        id: '01hevidence3',
                        lifecycle: 'submitted',
                        type: 'response',
                        related_intervention: {
                            id: '01hintervention1',
                            type: 'intervention',
                            occurred_at: '2026-09-06T09:00:00Z',
                            provision: { id: '01hprovision1', code: 'UNIVERSAL', label: 'Universal classroom strategies' },
                        },
                    },
                }),
            });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-mode-response"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-related-intervention"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-setting"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="capture-provision"]').exists()).toBe(false);

        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await flushPromises();

        expect(apiFetch).toHaveBeenCalledWith('/api/v1/pupils/01hpupil1/interventions');
        expect(wrapper.find('[data-testid="capture-related-intervention"]').text())
            .toContain('Universal classroom strategies');

        await wrapper.find('[data-testid="capture-related-intervention"]').setValue('01hintervention1');
        await wrapper.find('[data-testid="capture-body"]').setValue('Engaged calmly after the timetable.');
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(apiFetch).toHaveBeenLastCalledWith('/api/v1/responses', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({
                'X-Client-Type': 'web',
            }),
        }));

        const body = JSON.parse(apiFetch.mock.calls.at(-1)[1].body);
        expect(body.pupil_id).toBe('01hpupil1');
        expect(body.related_intervention_id).toBe('01hintervention1');
        expect(body.body).toBe('Engaged calmly after the timetable.');
        expect(body.client_type).toBe('web');
        expect(body.occurred_at).toMatch(/Z$/);

        expect(wrapper.find('[data-testid="capture-confirmation"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="capture-confirmation-id"]').text()).toContain('01hevidence3');
        expect(wrapper.find('[data-testid="capture-confirmation"]').text()).toContain('Pupil Response submitted');
        expect(wrapper.find('[data-testid="capture-confirmation-related-intervention"]').text())
            .toContain('Universal classroom strategies');
        expect(wrapper.find('[data-testid="capture-confirmation-related-intervention"]').text())
            .not.toContain('01hintervention1');
    });

    it('shows empty Intervention state in Pupil Response mode when none exist', async () => {
        mockLoadSuccess();
        apiFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ data: [] }),
        });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-mode-response"]').trigger('click');
        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-interventions-empty"]').text())
            .toContain('No Interventions recorded for this Pupil yet');
        expect(wrapper.find('[data-testid="capture-submit"]').attributes('disabled')).toBeUndefined();
    });

    it('surfaces related intervention field errors from a 422 in Pupil Response mode', async () => {
        mockLoadSuccess();
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [] }),
            })
            .mockResolvedValueOnce({
                ok: false,
                status: 422,
                json: async () => ({
                    message: 'The given data was invalid.',
                    errors: {
                        related_intervention_id: [
                            'The selected Intervention must belong to the same Pupil and be a submitted Intervention record.',
                        ],
                        body: ['Pupil Response notes are required.'],
                    },
                }),
            });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-mode-response"]').trigger('click');
        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await flushPromises();
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-related-intervention-error"]').text())
            .toContain('The selected Intervention must belong to the same Pupil');
        expect(wrapper.find('[data-testid="capture-body-error"]').text())
            .toContain('Pupil Response notes are required.');
    });

    it('shows submit error when Pupil Response response is ok but missing data.id', async () => {
        mockLoadSuccess();
        apiFetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 201,
                json: async () => ({ data: {} }),
            });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-mode-response"]').trigger('click');
        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await flushPromises();
        await wrapper.find('[data-testid="capture-body"]').setValue('Engaged calmly.');
        await wrapper.find('[data-testid="capture-occurred-at"]').setValue('2026-09-06T10:15');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-confirmation"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="capture-submit-error"]').text())
            .toContain('Pupil Response was accepted but no record reference was returned.');
    });

    it('shows Interventions load failure message in Pupil Response mode', async () => {
        mockLoadSuccess();
        apiFetch.mockResolvedValueOnce({ ok: false });

        const wrapper = mount(CapturePage);
        await flushPromises();

        await wrapper.find('[data-testid="capture-mode-response"]').trigger('click');
        await wrapper.find('[data-testid="capture-pupil"]').setValue('01hpupil1');
        await flushPromises();

        expect(wrapper.find('[data-testid="capture-interventions-empty"]').text())
            .toContain('Unable to load Interventions for this Pupil.');
    });
});
