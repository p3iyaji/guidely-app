/** @vitest-environment jsdom */

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FeatureFlaggedEmpty from '../shared/ui/FeatureFlaggedEmpty.vue';

describe('FeatureFlaggedEmpty', () => {
    it('shows Not available for this Tenant copy', () => {
        const wrapper = mount(FeatureFlaggedEmpty);

        expect(wrapper.text()).toContain('Not available for this Tenant');
        expect(wrapper.attributes('role')).toBe('status');
    });
});
