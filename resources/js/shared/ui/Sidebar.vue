<template>
    <aside
        class="flex w-sidebar shrink-0 flex-col border-r border-border bg-sidebar"
        data-testid="sidebar"
        :aria-label="ariaLabel"
    >
        <nav class="flex flex-1 flex-col gap-1 p-3" aria-label="Primary">
            <RouterLink
                v-for="item in items"
                :key="item.key"
                :to="item.to"
                class="rounded-md px-3 py-2 text-body text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                :class="isNavItemActive(route, item.to) ? 'bg-surface-muted font-medium text-primary' : ''"
                data-testid="sidebar-item"
            >
                {{ item.label }}
            </RouterLink>
        </nav>
    </aside>
</template>

<script setup>
import { RouterLink, useRoute } from 'vue-router';
import { isNavItemActive } from '../../features/shell/isNavItemActive';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    ariaLabel: {
        type: String,
        default: 'Role navigation',
    },
});

const route = useRoute();
</script>
