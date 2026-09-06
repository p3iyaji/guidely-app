<template>
    <div class="flex min-h-screen flex-col bg-canvas" data-testid="app-shell">
        <TopBar
            :user-name="resolvedUserName"
            :show-menu-toggle="showMenuToggle"
            :aria-expanded="navOpen"
            @toggle-nav="navOpen = !navOpen"
        />

        <div class="relative flex min-h-0 flex-1">
            <!-- Desktop / large tablet sidebar (≥1024) -->
            <div class="hidden lg:flex">
                <Sidebar :items="navItems" />
            </div>

            <!-- Mid-width / mobile collapsible drawer -->
            <div
                v-if="navOpen"
                class="fixed inset-0 z-30 bg-black/40 lg:hidden"
                data-testid="nav-backdrop"
                @click="navOpen = false"
            />
            <div
                class="fixed inset-y-0 left-0 z-40 flex h-full transition-transform lg:hidden"
                :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
                :inert="!navOpen"
                :aria-hidden="navOpen ? 'false' : 'true'"
                data-testid="mobile-nav-panel"
            >
                <Sidebar :items="navItems" aria-label="Role navigation menu" />
            </div>

            <main
                class="min-w-0 flex-1 overflow-auto p-page"
                :class="showBottomNav ? 'pb-20' : ''"
            >
                <RouterView />
            </main>
        </div>

        <BottomNav v-if="showBottomNav" :items="bottomNavItems" />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import {
    navItemsForRole,
    TEACHER_SUPPORT_BOTTOM_NAV,
    usesTeacherSupportBottomNav,
} from '../features/shell/navByRole';
import { useSession } from '../features/auth/session';
import BottomNav from '../shared/ui/BottomNav.vue';
import Sidebar from '../shared/ui/Sidebar.vue';
import TopBar from '../shared/ui/TopBar.vue';

const props = defineProps({
    role: {
        type: String,
        default: null,
    },
    userName: {
        type: String,
        default: '',
    },
});

const route = useRoute();
const session = useSession();
const navOpen = ref(false);

const effectiveRole = computed(() => props.role ?? session.role.value);
const resolvedUserName = computed(() => {
    if (props.userName) {
        return props.userName;
    }

    return session.user.value?.name ?? '';
});
const navItems = computed(() => navItemsForRole(effectiveRole.value));
const showBottomNav = computed(() => usesTeacherSupportBottomNav(effectiveRole.value));
const bottomNavItems = computed(() => (showBottomNav.value ? TEACHER_SUPPORT_BOTTOM_NAV : []));
/** Hamburger for mid widths / Roles without relying on bottom nav alone. */
const showMenuToggle = computed(() => true);

watch(
    () => route.fullPath,
    () => {
        navOpen.value = false;
    },
);

function onKeydown(event) {
    if (event.key === 'Escape') {
        navOpen.value = false;
    }
}

/** @type {MediaQueryList|null} */
let largeViewportQuery = null;

function onLargeViewportChange(event) {
    if (event.matches) {
        navOpen.value = false;
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    if (typeof window.matchMedia === 'function') {
        largeViewportQuery = window.matchMedia('(min-width: 1024px)');
        largeViewportQuery.addEventListener('change', onLargeViewportChange);

        if (largeViewportQuery.matches) {
            navOpen.value = false;
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    largeViewportQuery?.removeEventListener('change', onLargeViewportChange);
});
</script>
