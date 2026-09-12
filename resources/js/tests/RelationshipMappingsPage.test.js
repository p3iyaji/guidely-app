/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSession } from '../features/auth/session.js';
import RelationshipMappingsPage from '../pages/RelationshipMappingsPage.vue';

const sampleMappings = [
    {
        id: 'rm_1',
        code: 'HIGH_RISK',
        label: 'High risk',
        relationship_type: 'need_to_provision',
        from_domain: 'need',
        from_term_id: 'nt_1',
        to_domain: 'provision',
        to_term_id: 'pt_1',
        is_active: true,
        sort_order: 1,
        ontology_version_id: 'ont_1',
        from_term_label: 'Needs Assessment',
        to_term_label: 'Provision of Support',
    },
    {
        id: 'rm_2',
        code: 'WATCH',
        label: 'Watch list & monitoring',
        relationship_type: 'need_to_outcome',
        from_domain: 'need',
        from_term_id: 'nt_2',
        to_domain: 'outcome',
        to_term_id: 'ot_1',
        is_active: false,
        sort_order: 6,
        ontology_version_id: 'ont_1',
        from_term_label: 'Needs Assessment',
        to_term_label: 'Outcome Achievement',
    },
];

describe('RelationshipMappingsPage', () => {
    /** @type {ReturnType<typeof vi.fn>} */
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/relationship-mappings') && method === 'GET') {
                return jsonResponse({ data: sampleMappings });
            }

            return jsonResponse({});
        });

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
     * @param {{ flush?: boolean }} [options]
     */
    async function mountPage(role = 'tenant_admin', options = {}) {
        const { flush = true } = options;

        useSession().setUser({
            id: 'usr_1',
            name: 'Test User',
            email: 'test@example.com',
            role,
            tenant_id: 'ten_1',
        });

        const wrapper = mount(RelationshipMappingsPage);

        if (flush) {
            await flushPromises();
        }

        return wrapper;
    }

    it('lists Relationship mappings with Active and Inactive status for Tenant Admin', async () => {
        const wrapper = await mountPage('tenant_admin');
        expect(wrapper.find('[data-testid="relationship-mappings-page"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).toBe('Relationship mappings');
        expect(wrapper.findAll('[data-testid="relationship-mapping-row"]').length).toBe(2);
        expect(wrapper.text()).toContain('High risk');
        expect(wrapper.text()).toContain('Watch list & monitoring');
        expect(wrapper.findAll('[data-testid="relationship-mapping-status"]').map((node) => node.text())).toEqual([
            'Active',
            'Inactive',
        ]);
        expect(wrapper.find('[data-testid="relationship-mappings-add-open"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="relationship-mappings-form"]').exists()).toBe(false);
    });

    it('opens Add Relationship mapping in a dialog and keeps the empty state behind it', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/api/v1/ontology/relationship-mappings')) {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();
        
        await wrapper.find('[data-testid="relationship-mappings-add-cta"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.find('[data-testid="relationship-mappings-form"]');
        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('role')).toBe('dialog');
        expect(wrapper.find('[data-testid="relationship-mappings-empty"]').exists()).toBe(true);
    });

    it('opens Edit Relationship mapping in a dialog from the list', async () => {
        const wrapper = await mountPage();
        
        await wrapper.find('[data-testid="relationship-mapping-edit-rm_1"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="relationship-mappings-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="relationship-mappings-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="relationship-mappings-form"]').text()).toContain('Edit Relationship mapping');
        expect(wrapper.find('[data-testid="relationship-mapping-code-input"]').element.value).toBe('HIGH_RISK');
        expect(wrapper.find('[data-testid="relationship-mapping-label-input"]').element.value).toBe(
            'High risk',
        );
    });

    it('hides add and edit actions for Teachers', async () => {
        const wrapper = await mountPage('teacher');
        expect(wrapper.find('[data-testid="relationship-mappings-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="relationship-mappings-add-open"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="relationship-mapping-edit-rm_1"]').exists()).toBe(false);
    });

    it('filters the list by code and label', async () => {
        const wrapper = await mountPage();
        
        await wrapper.find('[data-testid="relationship-mappings-search"]').setValue('WATCH');
        expect(wrapper.findAll('[data-testid="relationship-mapping-row"]').length).toBe(1);
        expect(wrapper.text()).toContain('Watch list & monitoring');

        await wrapper.find('[data-testid="relationship-mappings-search"]').setValue('HIGH_RISK');
        expect(wrapper.findAll('[data-testid="relationship-mapping-row"]').length).toBe(1);
        expect(wrapper.text()).toContain('High risk');
    });

    it('creates a Relationship mapping via POST with trimmed fields', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/relationship-mappings') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path.includes('/api/v1/ontology/relationship-mappings') && method === 'POST') {
                return jsonResponse(
                    {
                        data: {
                            id: 'rm_new',
                            code: 'HIGH_RISK',
                            label: 'High risk',
                            relationship_type: 'need_to_provision',
                            from_domain: 'need',
                            from_term_id: 'nt_1',
                            to_domain: 'provision',
                            to_term_id: 'pt_1',
                            is_active: true,
                            sort_order: 7,
                            ontology_version_id: 'ont_1',
                            from_term_label: 'Needs Assessment',
                            to_term_label: 'Provision of Support',
                        },
                    },
                    201,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();
        
        await wrapper.find('[data-testid="relationship-mappings-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="relationship-mapping-code-input"]').setValue('  high_risk  ');
        await wrapper.find('[data-testid="relationship-mapping-label-input"]').setValue('  High risk  ');
        await wrapper.find('[data-testid="relationship-mapping-relationship-type-input"]').setValue('need_to_provision');
        await wrapper.find('[data-testid="relationship-mapping-from-domain-select"]').setValue('need');
        await wrapper.find('[data-testid="relationship-mapping-from-term-id-input"]').setValue('nt_1');
        await wrapper.find('[data-testid="relationship-mapping-to-domain-select"]').setValue('provision');
        await wrapper.find('[data-testid="relationship-mapping-to-term-id-input"]').setValue('pt_1');
        await wrapper.find('[data-testid="relationship-mappings-form"] form').trigger('submit.prevent');
        await flushPromises();

        const postCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/relationship-mappings'
                && (options?.method ?? 'GET').toUpperCase() === 'POST',
        );

        expect(postCall).toBeTruthy();
        expect(JSON.parse(postCall[1].body)).toEqual({
            code: 'high_risk',
            label: 'High risk',
            relationship_type: 'need_to_provision',
            from_domain: 'need',
            from_term_id: 'nt_1',
            to_domain: 'provision',
            to_term_id: 'pt_1',
            sort_order: 7,
            is_active: true,
        });
        expect(wrapper.text()).toContain('High risk');
        expect(wrapper.find('[data-testid="relationship-mappings-form"]').exists()).toBe(false);
    });

    it('shows 422 inline code error on Add Relationship mapping', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/relationship-mappings') && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (method === 'POST') {
                return jsonResponse(
                    {
                        message: 'The given data was invalid.',
                        errors: { code: ['A Relationship mapping with this code already exists on this Ontology version.'] },
                    },
                    422,
                );
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();
        
        await wrapper.find('[data-testid="relationship-mappings-add-cta"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="relationship-mapping-code-input"]').setValue('HIGH_RISK');
        await wrapper.find('[data-testid="relationship-mapping-label-input"]').setValue('High risk');
        await wrapper.find('[data-testid="relationship-mapping-relationship-type-input"]').setValue('need_to_provision');
        await wrapper.find('[data-testid="relationship-mapping-from-domain-select"]').setValue('need');
        await wrapper.find('[data-testid="relationship-mapping-from-term-id-input"]').setValue('nt_1');
        await wrapper.find('[data-testid="relationship-mapping-to-domain-select"]').setValue('provision');
        await wrapper.find('[data-testid="relationship-mapping-to-term-id-input"]').setValue('pt_1');
        await wrapper.find('[data-testid="relationship-mappings-form"] form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[data-testid="error-code"]').text()).toContain('already exists');
        expect(wrapper.find('[data-testid="relationship-mappings-form"]').exists()).toBe(true);
    });

    it('updates a Relationship mapping via PATCH including inactive flag', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/relationship-mappings') && method === 'GET') {
                return jsonResponse({ data: sampleMappings });
            }

            if (path.includes('/api/v1/ontology/relationship-mappings/rm_1') && method === 'PATCH') {
                return jsonResponse({
                    data: {
                        id: 'rm_1',
                        code: 'HIGH_RISK',
                        label: 'High risk updated',
                        relationship_type: 'need_to_provision',
                        from_domain: 'need',
                        from_term_id: 'nt_1',
                        to_domain: 'provision',
                        to_term_id: 'pt_1',
                        is_active: false,
                        sort_order: 1,
                        ontology_version_id: 'ont_1',
                        from_term_label: 'Needs Assessment',
                        to_term_label: 'Provision of Support',
                    },
                });
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();
        
        await wrapper.find('[data-testid="relationship-mapping-edit-rm_1"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="relationship-mapping-label-input"]').setValue('High risk updated');
        await wrapper.find('[data-testid="relationship-mapping-active-input"]').setValue(false);
        await wrapper.find('[data-testid="relationship-mappings-form"] form').trigger('submit.prevent');
        await flushPromises();

        const patchCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/relationship-mappings/rm_1'
                && (options?.method ?? 'GET').toUpperCase() === 'PATCH',
        );

        expect(patchCall).toBeTruthy();
        expect(JSON.parse(patchCall[1].body)).toEqual({
            code: 'HIGH_RISK',
            label: 'High risk updated',
            relationship_type: 'need_to_provision',
            from_domain: 'need',
            from_term_id: 'nt_1',
            to_domain: 'provision',
            to_term_id: 'pt_1',
            sort_order: 1,
            is_active: false,
        });
        expect(wrapper.text()).toContain('High risk updated');
    });

    it('deletes a Relationship mapping via DELETE after confirm', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
            const path = String(url);
            const method = (options.method ?? 'GET').toUpperCase();

            if (path.includes('/api/v1/ontology/relationship-mappings') && method === 'GET') {
                return jsonResponse({ data: sampleMappings });
            }

            if (path.includes('/api/v1/ontology/relationship-mappings/rm_2') && method === 'DELETE') {
                return {
                    ok: true,
                    status: 204,
                    clone() {
                        return this;
                    },
                    async json() {
                        return {};
                    },
                };
            }

            return jsonResponse({});
        });

        const wrapper = await mountPage();
        
        await wrapper.find('[data-testid="relationship-mapping-delete-rm_2"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="relationship-mappings-delete-confirm"]').text()).toContain(
            'Watch list & monitoring',
        );

        await wrapper.find('[data-testid="relationship-mappings-delete-confirm-submit"]').trigger('click');
        await flushPromises();

        const deleteCall = fetchMock.mock.calls.find(
            ([url, options]) =>
                String(url) === '/api/v1/ontology/relationship-mappings/rm_2'
                && (options?.method ?? 'GET').toUpperCase() === 'DELETE',
        );

        expect(deleteCall).toBeTruthy();
        expect(wrapper.text()).not.toContain('Watch list & monitoring');
        expect(wrapper.findAll('[data-testid="relationship-mapping-row"]').length).toBe(1);
    });
});

/**
 * @param {unknown} body
 * @param {number} [status]
 */
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