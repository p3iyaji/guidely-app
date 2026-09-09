<template>
    <div class="flex min-h-screen bg-canvas" data-testid="app-shell">
        <div class="sticky top-0 hidden h-screen shrink-0 lg:flex">
            <Sidebar :items="navItems" :workspace-label="workspaceLabel" />
        </div>

        <div
            v-if="navOpen"
            class="fixed inset-0 z-30 bg-black/45 lg:hidden"
            data-testid="nav-backdrop"
            @click="navOpen = false"
        />
        <div
            class="fixed inset-y-0 left-0 z-40 flex h-full transition-transform duration-200 lg:hidden"
            :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
            :inert="!navOpen"
            :aria-hidden="navOpen ? 'false' : 'true'"
            data-testid="mobile-nav-panel"
        >
            <Sidebar
                :items="navItems"
                :workspace-label="workspaceLabel"
                aria-label="Role navigation menu"
            />
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <TopBar
                :user-name="resolvedUserName"
                :show-menu-toggle="showMenuToggle"
                :aria-expanded="navOpen"
                :signing-out="signingOut"
                :can-search-review-cycles="reviewCycleSearchEnabled"
                :page-title="pageTitle"
                :workspace-label="workspaceLabel"
                :role-label="roleLabel"
                @toggle-nav="navOpen = !navOpen"
                @sign-out="onSignOut"
            />
            <main
                class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                :class="showBottomNav ? 'pb-20' : ''"
            >
                <div class="mx-auto w-full max-w-[1600px]">
                    <RouterView />
                </div>
            </main>
        </div>

        <BottomNav v-if="showBottomNav" :items="bottomNavItems" />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import {
    canSearchReviewCycles,
    navItemsForRole,
    TEACHER_SUPPORT_BOTTOM_NAV,
    usesTeacherSupportBottomNav,
} from '../features/shell/navByRole';
import { useSession } from '../features/auth/session';
import { startOfflineFlushListener } from '../features/evidence/startOfflineFlushListener';
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
const router = useRouter();
const session = useSession();
const navOpen = ref(false);
const signingOut = ref(false);
/** @type {(() => void)|null} */
let stopOfflineFlushListener = null;

async function onSignOut() {
    if (signingOut.value) {
        return;
    }

    signingOut.value = true;

    try {
        await session.clearSession();
        await router.push({ name: 'login' });
    } finally {
        signingOut.value = false;
    }
}

const effectiveRole = computed(() => props.role ?? session.role.value);
const resolvedUserName = computed(() => {
    if (props.userName) {
        return props.userName;
    }

    return session.user.value?.name ?? '';
});
const navItems = computed(() => navItemsForRole(effectiveRole.value));
const reviewCycleSearchEnabled = computed(() => canSearchReviewCycles(effectiveRole.value));
const showBottomNav = computed(() => usesTeacherSupportBottomNav(effectiveRole.value));
const bottomNavItems = computed(() => (showBottomNav.value ? TEACHER_SUPPORT_BOTTOM_NAV : []));
const roleLabel = computed(() => {
    const role = effectiveRole.value;

    if (!role) {
        return 'Staff';
    }

    if (String(role).toLowerCase() === 'senco') {
        return 'SENCO';
    }

    return String(role)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
});
const workspaceLabel = computed(() => `${roleLabel.value} workspace`);
const pageTitle = computed(() => route.meta.title ?? (
    effectiveRole.value === 'trust_send_lead' || effectiveRole.value === 'trust_executive'
        ? 'Trust overview'
        : 'Dashboard'
));
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
    stopOfflineFlushListener = startOfflineFlushListener();

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
    stopOfflineFlushListener?.();
    stopOfflineFlushListener = null;
});
</script>
