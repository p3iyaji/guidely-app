/** @vitest-environment jsdom */

import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import Modal from '../shared/ui/Modal.vue';

describe('Modal', () => {
    afterEach(() => {
        document.body.style.overflow = '';
    });

    it('does not render when closed', () => {
        const wrapper = mount(Modal, {
            props: { open: false, title: 'Add User' },
        });

        expect(wrapper.find('[data-testid="modal"]').exists()).toBe(false);
    });

    it('renders a labelled dialog when open', () => {
        const wrapper = mount(Modal, {
            props: { open: true, title: 'Add User' },
            slots: { default: '<p>Body</p>' },
        });

        const dialog = wrapper.find('[role="dialog"]');

        expect(wrapper.find('[data-testid="modal"]').exists()).toBe(true);
        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(wrapper.text()).toContain('Add User');
        expect(wrapper.text()).toContain('Body');
        expect(document.body.style.overflow).toBe('hidden');
    });

    it('emits close from backdrop, close button, and Escape', async () => {
        const wrapper = mount(Modal, {
            props: { open: true, title: 'Edit User' },
        });

        await wrapper.find('[data-testid="modal-backdrop"]').trigger('click');
        await wrapper.find('[data-testid="modal-close"]').trigger('click');
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('close')?.length).toBe(3);
    });

    it('does not emit close when close is disabled', async () => {
        const wrapper = mount(Modal, {
            props: { open: true, title: 'Edit User', closeDisabled: true },
        });

        await wrapper.find('[data-testid="modal-backdrop"]').trigger('click');
        await wrapper.find('[data-testid="modal-close"]').trigger('click');
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('close')).toBeUndefined();
        expect(wrapper.find('[data-testid="modal-close"]').attributes('disabled')).toBeDefined();
    });
});
