<template>
    <aside
        class="flex h-full w-sidebar shrink-0 flex-col bg-sidebar text-white"
        data-testid="sidebar"
        :aria-label="ariaLabel"
    >
        <div class="flex h-18 shrink-0 items-center border-b border-white/15 px-5">
            <BrandWordmark />
        </div>

        <div class="border-b border-white/15 px-4 py-4">
            <div class="flex min-h-12 items-center gap-3 rounded-md border border-white/20 bg-white/10 px-3">
                <AppIcon name="schools" class="size-4.5 shrink-0 text-blue-100" />
                <span class="min-w-0">
                    <span class="block text-[11px] text-white/60">Active workspace</span>
                    <span class="block truncate text-body font-medium text-white">{{ workspaceLabel }}</span>
                </span>
            </div>
        </div>

        <nav class="flex flex-1 flex-col overflow-y-auto px-3 py-5" aria-label="Primary">
            <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-white/50">
                Navigation
            </p>
            <template v-for="item in items" :key="item.key">
                <RouterLink
                    v-if="!item.children?.length"
                    :to="item.to"
                    class="mb-1 flex min-h-10 items-center gap-3 rounded-md px-3 text-body font-medium text-white/75 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/80"
                    :class="isNavItemActive(route, item.to) ? 'bg-white/15 text-white' : ''"
                    data-testid="sidebar-item"
                >
                    <AppIcon :name="item.key" class="size-4.5 shrink-0" />
                    <span>{{ item.label }}</span>
                </RouterLink>
                <div
                    v-else
                    class="mt-2 flex flex-col"
                    data-testid="sidebar-group"
                    :data-group-key="item.key"
                >
                    <button
                        type="button"
                        class="mb-1 flex min-h-10 w-full items-center gap-3 rounded-md px-3 text-left text-body font-semibold text-white/85 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/80"
                        :class="isGroupActive(item) ? 'text-white' : ''"
                        :aria-expanded="isGroupOpen(item)"
                        :aria-controls="groupPanelId(item.key)"
                        data-testid="sidebar-group-toggle"
                        @click="toggleGroup(item.key)"
                    >
                        <AppIcon :name="item.key" class="size-4.5 shrink-0 text-white/75" />
                        <span class="flex-1">{{ item.label }}</span>
                        <span
                            class="inline-block text-meta text-white/55 transition-transform"
                            :class="isGroupOpen(item) ? '' : '-rotate-90'"
                            aria-hidden="true"
                        >
                            ▾
                        </span>
                    </button>
                    <div
                        :id="groupPanelId(item.key)"
                        class="ml-5 flex flex-col border-l border-white/15 pl-2"
                        data-testid="sidebar-group-panel"
                        :hidden="!isGroupOpen(item)"
                    >
                        <RouterLink
                            v-for="child in item.children"
                            :key="child.key"
                            :to="child.to"
                            class="mb-1 flex min-h-9 items-center gap-3 rounded-md px-3 text-[13px] font-medium text-white/70 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/80"
                            :class="isNavItemActive(route, child.to) ? 'bg-white/15 text-white' : ''"
                            data-testid="sidebar-item"
                        >
                            <AppIcon :name="child.key" class="size-4 shrink-0" />
                            <span>{{ child.label }}</span>
                        </RouterLink>
                    </div>
                </div>
            </template>
        </nav>

        <div class="border-t border-white/15 p-3">
            <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-white/50">
                My account
            </p>
            <RouterLink
                to="/profile"
                class="flex min-h-10 items-center gap-3 rounded-md px-3 text-body font-medium text-white/75 hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/80"
            >
                <AppIcon name="profile" class="size-4.5" />
                Profile
            </RouterLink>
        </div>
    </aside>
</template>

<script setup>
import { ref, useId, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { isNavItemActive } from '../../features/shell/isNavItemActive';
import AppIcon from './AppIcon.vue';
import BrandWordmark from './BrandWordmark.vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    ariaLabel: {
        type: String,
        default: 'Role navigation',
    },
    workspaceLabel: {
        type: String,
        default: 'GuidelyEdu',
    },
});

const route = useRoute();
const instanceId = useId();
/** @type {import('vue').Ref<Record<string, boolean>>} */
const userOpen = ref({});

function groupPanelId(key) {
    return `nav-group-${instanceId}-${key}`;
}

function isGroupActive(item) {
    return (item.children ?? []).some((child) => isNavItemActive(route, child.to));
}

function isGroupOpen(item) {
    if (Object.prototype.hasOwnProperty.call(userOpen.value, item.key)) {
        return userOpen.value[item.key];
    }

    return isGroupActive(item);
}

function toggleGroup(key) {
    const item = props.items.find((entry) => entry.key === key);

    if (!item) {
        return;
    }

    userOpen.value = {
        ...userOpen.value,
        [key]: !isGroupOpen(item),
    };
}

watch(
    () => route.fullPath,
    () => {
        userOpen.value = {};
    },
);
</script>
