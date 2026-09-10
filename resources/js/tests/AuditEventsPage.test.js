/** @vitest-environment jsdom */

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AuditEventsPage from '../pages/AuditEventsPage.vue';

const sampleEvent = {
    id: '01J00000000000000000000000',
    event_type: 'user.updated',
    user_id: '1',
    user: {
        id: '1',
        name: 'Ada Admin',
        email: 'ada@example.com',
    },
    resource_type: 'user',
    resource_id: '2',
    ip: '203.0.113.10',
    user_agent: 'Test Browser',
    metadata: {
        field: 'name',
    },
    created_at: '2026-09-10T01:30:00.000000Z',
};

describe('AuditEventsPage', () => {
    let fetchMock;

    beforeEach(() => {
        fetchMock = vi.fn(async (url) => {
            if (String(url) === `/api/v1/audit-events/${sampleEvent.id}`) {
                return jsonResponse({ data: sampleEvent });
            }

            return jsonResponse({
                data: [sampleEvent],
                meta: {
                    current_page: 1,
                    last_page: 2,
                },
            });
        });
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('lists immutable audit events with pagination', async () => {
        const wrapper = mount(AuditEventsPage);

        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/v1/audit-events?page=1',
            expect.any(Object),
        );
        expect(wrapper.find('h1').text()).toBe('Audit events');
        expect(wrapper.findAll('[data-testid="audit-event-row"]')).toHaveLength(1);
        expect(wrapper.find('[data-testid="audit-event-type"]').text()).toBe('user.updated');
        expect(wrapper.text()).toContain('Ada Admin');
        expect(wrapper.text()).toContain('Page 1 of 2');
        expect(wrapper.find('[data-testid*="audit-event-edit"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid*="audit-event-delete"]').exists()).toBe(false);
    });

    it('loads and displays event details', async () => {
        const wrapper = mount(AuditEventsPage);
        await flushPromises();

        await wrapper.find(`[data-testid="audit-event-view-${sampleEvent.id}"]`).trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            `/api/v1/audit-events/${sampleEvent.id}`,
            expect.any(Object),
        );
        expect(wrapper.find('[data-testid="audit-event-details"]').text()).toContain('ada@example.com');
        expect(wrapper.find('[data-testid="audit-event-details"]').text()).toContain('203.0.113.10');
        expect(wrapper.find('[data-testid="audit-event-details"]').text()).toContain('"field": "name"');
    });

    it('requests the next page', async () => {
        fetchMock.mockResolvedValueOnce(jsonResponse({
            data: [sampleEvent],
            meta: {
                current_page: 1,
                last_page: 2,
            },
        })).mockResolvedValueOnce(jsonResponse({
            data: [],
            meta: {
                current_page: 2,
                last_page: 2,
            },
        }));
        const wrapper = mount(AuditEventsPage);
        await flushPromises();

        await wrapper.find('[data-testid="audit-events-next"]').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenLastCalledWith(
            '/api/v1/audit-events?page=2',
            expect.any(Object),
        );
    });

    it('shows an access error for a forbidden response', async () => {
        fetchMock.mockResolvedValueOnce(jsonResponse({
            message: 'You do not have permission to perform this action.',
            code: 'forbidden',
        }, 403));

        const wrapper = mount(AuditEventsPage);
        await flushPromises();

        expect(wrapper.find('[data-testid="audit-events-error"]').text()).toBe('You don’t have access.');
    });
});

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
