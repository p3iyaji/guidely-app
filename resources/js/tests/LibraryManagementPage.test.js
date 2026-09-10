/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LibraryManagementPage from '../pages/LibraryManagementPage.vue';

const initialData = {
    tenants: [
        {
            id: 'ten_1',
            name: 'Alpha School',
            type: 'school',
            current_ontology_version: {
                id: 'ont_1',
                code: 'ontology-v1',
                label: 'Ontology V1',
                status: 'published',
                published_at: '2026-09-01T10:00:00.000000Z',
            },
            current_rule_library_version: null,
        },
    ],
    ontology_versions: [
        {
            id: 'ont_1',
            code: 'ontology-v1',
            label: 'Ontology V1',
            status: 'published',
            published_at: '2026-09-01T10:00:00.000000Z',
        },
        {
            id: 'ont_2',
            code: 'ontology-v2',
            label: 'Ontology V2',
            status: 'draft',
            published_at: null,
        },
    ],
    rule_library_versions: [
        {
            id: 'rule_2',
            code: 'rules-v2',
            label: 'Rules V2',
            status: 'draft',
            published_at: null,
        },
    ],
};

function jsonResponse(payload, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        clone() {
            return this;
        },
        async json() {
            return payload;
        },
    };
}

describe('LibraryManagementPage', () => {
    beforeEach(() => {
        let published = false;

        vi.stubGlobal('fetch', vi.fn(async (url, options = {}) => {
            const method = (options.method ?? 'GET').toUpperCase();

            if (method === 'PATCH') {
                published = true;

                return jsonResponse({
                    data: {
                        tenant: {
                            ...initialData.tenants[0],
                            current_rule_library_version: initialData.rule_library_versions[0],
                        },
                        result: {
                            ontology_published: false,
                            rule_library_published: true,
                            ontology_pin_changed: false,
                            rule_library_pin_changed: true,
                            queued_count: 2,
                        },
                    },
                });
            }

            const data = published
                ? {
                    ...initialData,
                    tenants: [{
                        ...initialData.tenants[0],
                        current_rule_library_version: {
                            ...initialData.rule_library_versions[0],
                            status: 'published',
                        },
                    }],
                }
                : initialData;

            return jsonResponse({ data });
        }));

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

    it('loads tenants, current pins, and available versions', async () => {
        const wrapper = mount(LibraryManagementPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="library-tenant-select"]').element.value).toBe('ten_1');
        expect(wrapper.find('[data-testid="current-ontology-pin"]').text()).toContain('Ontology V1');
        expect(wrapper.find('[data-testid="current-rule-library-pin"]').text()).toBe('Not pinned');
        expect(wrapper.find('[data-testid="ontology-version-select"]').text()).toContain('Ontology V2');
        expect(wrapper.find('[data-testid="rule-library-version-select"]').text()).toContain('Rules V2');
        expect(wrapper.find('[data-testid="library-publish-submit"]').attributes('disabled')).toBeDefined();
    });

    it('publishes either selected library and confirms queued reevaluations', async () => {
        const wrapper = mount(LibraryManagementPage);
        await flushPromises();

        await wrapper.find('[data-testid="rule-library-version-select"]').setValue('rule_2');
        await wrapper.find('[data-testid="library-management-form"]').trigger('submit.prevent');
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            '/api/v1/operator/library-management/ten_1',
            expect.objectContaining({
                method: 'PATCH',
                body: JSON.stringify({
                    ontology_version_id: null,
                    rule_library_version_id: 'rule_2',
                }),
            }),
        );
        expect(wrapper.find('[data-testid="library-publish-success"]').text()).toContain(
            '2 SRE re-evaluations queued',
        );
        expect(wrapper.find('[data-testid="current-rule-library-pin"]').text()).toContain('Rules V2');
    });

    it('shows an empty state when there are no tenants', async () => {
        fetch.mockResolvedValue(jsonResponse({
            data: {
                tenants: [],
                ontology_versions: [],
                rule_library_versions: [],
            },
        }));

        const wrapper = mount(LibraryManagementPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="library-management-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="library-management-form"]').exists()).toBe(false);
    });

    it('shows a load error with a retry action', async () => {
        fetch.mockResolvedValue(jsonResponse({ message: 'Service unavailable.' }, 503));

        const wrapper = mount(LibraryManagementPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="library-management-error"]').text()).toContain(
            'Service unavailable.',
        );
        expect(wrapper.find('[data-testid="library-management-error"] button').text()).toBe('Try again');
    });

    it('shows loading state while the request is pending', () => {
        fetch.mockReturnValue(new Promise(() => {}));

        const wrapper = mount(LibraryManagementPage);

        expect(wrapper.find('[data-testid="library-management-loading"]').exists()).toBe(true);
    });
});
