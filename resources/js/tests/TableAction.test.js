/** @vitest-environment jsdom */

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TableAction from '../shared/ui/TableAction.vue';

describe('TableAction', () => {
    it('renders an accessible icon-only action', () => {
        const wrapper = mount(TableAction, {
            props: {
                icon: 'edit',
                label: 'Edit pupil',
            },
        });

        const button = wrapper.get('button');

        expect(button.attributes('aria-label')).toBe('Edit pupil');
        expect(button.attributes('title')).toBe('Edit pupil');
        expect(button.find('svg').exists()).toBe(true);
        expect(button.find('.sr-only').text()).toBe('Edit pupil');
    });

    it('preserves disabled button behavior', async () => {
        const wrapper = mount(TableAction, {
            props: {
                disabled: true,
                icon: 'delete',
                label: 'Delete school',
                tone: 'danger',
            },
        });

        await wrapper.get('button').trigger('click');

        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
        expect(wrapper.emitted('click')).toBeUndefined();
    });
});
