<template>
    <!-- Visible below md (&lt;768); hidden from md up — DESIGN Hybrid breakpoint -->
    <nav
        class="fixed inset-x-0 bottom-0 z-20 flex h-16 items-stretch border-t border-border bg-surface md:hidden"
        data-testid="bottom-nav"
        aria-label="Primary"
    >
        <RouterLink
            v-for="item in items"
            :key="item.key"
            :to="item.to"
            class="relative flex flex-1 flex-col items-center justify-center gap-1 px-1 text-[11px] text-text-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus-ring"
            :class="isNavItemActive(route, item.to) ? 'font-medium text-primary' : ''"
            data-testid="bottom-nav-item"
        >
            <span
                class="grid size-7 place-items-center rounded-full"
                :class="item.key === 'capture' ? 'bg-primary text-white' : ''"
            >
                <AppIcon :name="item.key" class="size-4.5" />
            </span>
            <span>{{ item.label }}</span>
        </RouterLink>
    </nav>
</template>

<script setup>
import { RouterLink, useRoute } from 'vue-router';
import { isNavItemActive } from '../../features/shell/isNavItemActive';
import AppIcon from './AppIcon.vue';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const route = useRoute();
</script>
