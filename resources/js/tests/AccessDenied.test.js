/** @vitest-environment jsdom */

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AccessDenied from '../pages/AccessDenied.vue';
import { routes } from '../router/index.js';

describe('access-denied route', () => {
    it('registers /access-denied bound to AccessDenied', () => {
        const route = routes.find((entry) => entry.path === '/access-denied');

        expect(route).toBeDefined();
        expect(route?.name).toBe('access-denied');
        expect(route?.component).toBe(AccessDenied);
    });
});

describe('AccessDenied', () => {
    it('shows exact You don’t have access copy', () => {
        const wrapper = mount(AccessDenied);

        expect(wrapper.find('h1').text()).toBe('You don’t have access');
        expect(wrapper.find('[data-testid="access-denied"]').attributes('role')).toBe('alert');
    });
});
