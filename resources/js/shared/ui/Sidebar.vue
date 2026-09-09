<template>
    <aside
        class="flex w-sidebar shrink-0 flex-col border-r border-border bg-sidebar"
        data-testid="sidebar"
        :aria-label="ariaLabel"
    >
        <nav class="flex flex-1 flex-col gap-1 p-3" aria-label="Primary">
            <template v-for="item in items" :key="item.key">
                <RouterLink
                    v-if="!item.children?.length"
                    :to="item.to"
                    class="rounded-md px-3 py-2 text-body text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    :class="isNavItemActive(route, item.to) ? 'bg-surface-muted font-medium text-primary' : ''"
                    data-testid="sidebar-item"
                >
                    {{ item.label }}
                </RouterLink>
                <div
                    v-else
                    class="flex flex-col gap-1"
                    data-testid="sidebar-group"
                    :data-group-key="item.key"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-body text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                        :class="isGroupActive(item) ? 'font-medium text-primary' : ''"
                        :aria-expanded="isGroupOpen(item)"
                        :aria-controls="groupPanelId(item.key)"
                        data-testid="sidebar-group-toggle"
                        @click="toggleGroup(item.key)"
                    >
                        <span>{{ item.label }}</span>
                        <span
                            class="inline-block text-meta text-text-muted transition-transform"
                            :class="isGroupOpen(item) ? 'rotate-180' : ''"
                            aria-hidden="true"
                        >
                            ▾
                        </span>
                    </button>
                    <div
                        :id="groupPanelId(item.key)"
                        class="flex flex-col gap-1 pl-3"
                        data-testid="sidebar-group-panel"
                        :hidden="!isGroupOpen(item)"
                    >
                        <RouterLink
                            v-for="child in item.children"
                            :key="child.key"
                            :to="child.to"
                            class="rounded-md px-3 py-2 text-body text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring"
                            :class="isNavItemActive(route, child.to) ? 'bg-surface-muted font-medium text-primary' : ''"
                            data-testid="sidebar-item"
                        >
                            {{ child.label }}
                        </RouterLink>
                    </div>
                </div>
            </template>
        </nav>
    </aside>
</template>

<script setup>
import { ref, useId, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { isNavItemActive } from '../../features/shell/isNavItemActive';

const props = defineProps({
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
