<template>
    <!-- Visible below md (&lt;768); hidden from md up — DESIGN Hybrid breakpoint -->
    <nav
        class="fixed inset-x-0 bottom-0 z-20 flex h-14 items-stretch border-t border-border bg-surface md:hidden"
        data-testid="bottom-nav"
        aria-label="Primary"
    >
        <RouterLink
            v-for="item in items"
            :key="item.key"
            :to="item.to"
            class="flex flex-1 flex-col items-center justify-center gap-0.5 px-1 text-meta text-text-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus-ring"
            :class="isNavItemActive(route, item.to) ? 'font-medium text-primary' : ''"
            data-testid="bottom-nav-item"
        >
            {{ item.label }}
        </RouterLink>
    </nav>
</template>

<script setup>
import { RouterLink, useRoute } from 'vue-router';
import { isNavItemActive } from '../../features/shell/isNavItemActive';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const route = useRoute();
</script>
