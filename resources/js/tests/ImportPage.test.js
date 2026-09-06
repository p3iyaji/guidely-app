/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ImportPage from '../pages/ImportPage.vue';

vi.mock('../api/client.js', () => ({
    apiFetch: vi.fn(),
}));

import { apiFetch } from '../api/client.js';

describe('ImportPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        URL.createObjectURL = vi.fn(() => 'blob:import');
        URL.revokeObjectURL = vi.fn();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('downloads import template', async () => {
        apiFetch.mockResolvedValueOnce({
            ok: true,
            blob: async () => new Blob(['pupil_identifier'], { type: 'text/csv' }),
        });

        const wrapper = mount(ImportPage);
        const createElement = document.createElement.bind(document);
        const click = vi.fn();
        vi.spyOn(document, 'createElement').mockImplementation((tag) => {
            if (tag === 'a') {
                return { href: '', download: '', click };
            }

            return createElement(tag);
        });

        await wrapper.find('[data-testid="import-download-template"]').trigger('click');
        await flushPromises();

        expect(apiFetch).toHaveBeenCalledWith('/api/v1/import/template');
        expect(click).toHaveBeenCalled();
    });

    it('shows template download failure alert', async () => {
        apiFetch.mockResolvedValueOnce({ ok: false });

        const wrapper = mount(ImportPage);
        await wrapper.find('[data-testid="import-download-template"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="import-template-error"]').text())
            .toContain('Unable to download Import Template.');
    });

    it('uploads csv and renders committed and error rows', async () => {
        apiFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                data: {
                    committed: [
                        {
                            row: 2,
                            action: 'created',
                            id: '01hpupil1',
                            given_name: 'Alex',
                            family_name: 'Taylor',
                            mis_key: 'MIS-100',
                        },
                    ],
                    errors: [
                        { row: 3, message: 'Given name is required.' },
                    ],
                    summary: {
                        committed_count: 1,
                        error_count: 1,
                    },
                },
            }),
        });

        const wrapper = mount(ImportPage);
        const input = wrapper.find('[data-testid="import-file-input"]');
        const file = new File(['csv'], 'pupils.csv', { type: 'text/csv' });

        Object.defineProperty(input.element, 'files', {
            value: [file],
            configurable: true,
        });
        await input.trigger('change');

        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(apiFetch).toHaveBeenCalledWith(
            '/api/v1/import/pupils',
            expect.objectContaining({
                method: 'POST',
                body: expect.any(FormData),
            }),
        );

        const [, options] = apiFetch.mock.calls[0];
        expect(options.body.get('file')).toBe(file);

        expect(wrapper.find('[data-testid="import-results-summary"]').text())
            .toContain('Committed 1');
        expect(wrapper.find('[data-testid="import-committed-row"]').text())
            .toContain('Alex Taylor');
        expect(wrapper.find('[data-testid="import-error-row"]').text())
            .toContain('Given name is required.');
    });

    it('shows upload error when API rejects the file', async () => {
        apiFetch.mockResolvedValueOnce({
            ok: false,
            json: async () => ({
                message: 'The CSV file is empty or missing a header row.',
                errors: { file: ['The CSV file is empty or missing a header row.'] },
            }),
        });

        const wrapper = mount(ImportPage);
        const input = wrapper.find('[data-testid="import-file-input"]');
        const file = new File([''], 'empty.csv', { type: 'text/csv' });

        Object.defineProperty(input.element, 'files', {
            value: [file],
            configurable: true,
        });
        await input.trigger('change');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="import-upload-error"]').text())
            .toContain('The CSV file is empty or missing a header row.');
    });
});
