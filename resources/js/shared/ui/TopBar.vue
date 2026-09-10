<template>
    <header
        class="sticky top-0 z-20 flex min-h-16 shrink-0 items-center gap-3 border-b border-border bg-topbar px-4 text-text sm:px-6 lg:px-8"
        data-testid="top-bar"
    >
        <button
            v-if="showMenuToggle"
            type="button"
            class="inline-flex size-11 shrink-0 items-center justify-center rounded-md border border-border text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring lg:hidden"
            aria-label="Toggle navigation"
            :aria-expanded="ariaExpanded"
            data-testid="nav-toggle"
            @click="$emit('toggle-nav')"
        >
            <AppIcon name="menu" class="size-5" />
        </button>

        <div class="min-w-0">
            <p class="truncate text-body font-semibold text-text">{{ pageTitle }}</p>
            <p class="truncate text-meta text-text-muted">{{ workspaceLabel }}</p>
        </div>

        <div class="ml-auto flex min-w-0 items-center justify-end gap-3 sm:gap-4">
            <form
                v-if="searchScopes.length > 0"
                class="hidden items-center gap-2 lg:flex"
                data-testid="shell-search"
                @submit.prevent="onSearch"
            >
                <label v-if="searchScopes.length > 1" class="sr-only" for="shell-search-scope">
                    Search scope
                </label>
                <select
                    v-if="searchScopes.length > 1"
                    id="shell-search-scope"
                    v-model="selectedScopeKey"
                    class="w-32 rounded-md border border-border bg-surface-muted px-2 py-2 text-body text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    data-testid="shell-search-scope"
                >
                    <option
                        v-for="scope in searchScopes"
                        :key="scope.key"
                        :value="scope.key"
                    >
                        {{ scope.label }}
                    </option>
                </select>
                <label class="sr-only" for="shell-search">Search</label>
                <input
                    id="shell-search"
                    v-model="searchQuery"
                    type="search"
                    :placeholder="selectedScope?.placeholder ?? 'Search'"
                    class="w-56 rounded-md border border-border bg-surface-muted px-3 py-2 text-body text-text placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-focus-ring xl:w-72"
                    :title="`Search is limited to ${selectedScope?.label ?? 'records'} within your scope`"
                >
            </form>

            <div class="hidden h-8 w-px bg-border sm:block" />
            <div v-if="userName" class="hidden min-w-0 text-right sm:block">
                <p class="max-w-40 truncate text-body font-semibold text-text">{{ userName }}</p>
                <p class="max-w-40 truncate text-meta text-text-muted">{{ roleLabel }}</p>
            </div>

            <div ref="accountMenuRoot" class="relative" data-testid="account-menu">
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-label font-semibold text-primary-foreground hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-focus-ring"
                    :title="userName"
                    :aria-label="accountMenuOpen ? 'Close account menu' : 'Open account menu'"
                    :aria-expanded="accountMenuOpen"
                    aria-haspopup="menu"
                    data-testid="avatar"
                    @click="accountMenuOpen = !accountMenuOpen"
                >
                    {{ initials }}
                </button>

                <div
                    v-if="accountMenuOpen"
                    class="absolute right-0 z-50 mt-2 min-w-44 rounded-md border border-border bg-surface py-1 shadow-[0_1px_3px_rgba(31,41,55,0.12)]"
                    role="menu"
                    aria-label="Account"
                    data-testid="account-menu-panel"
                >
                    <p
                        v-if="userName"
                        class="truncate border-b border-border px-3 py-2 text-label text-text-muted"
                        data-testid="account-menu-name"
                    >
                        {{ userName }}
                    </p>
                    <RouterLink
                        to="/profile"
                        role="menuitem"
                        class="block w-full px-3 py-2 text-left text-body text-text hover:bg-surface-muted focus:outline-none focus:bg-surface-muted"
                        data-testid="account-menu-profile"
                        @click="accountMenuOpen = false"
                    >
                        Profile
                    </RouterLink>
                    <button
                        type="button"
                        role="menuitem"
                        class="block w-full px-3 py-2 text-left text-body text-text hover:bg-surface-muted focus:outline-none focus:bg-surface-muted"
                        data-testid="sign-out"
                        :disabled="signingOut"
                        @click="onSignOut"
                    >
                        {{ signingOut ? 'Signing out…' : 'Sign out' }}
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    userName: {
        type: String,
        default: '',
    },
    showMenuToggle: {
        type: Boolean,
        default: false,
    },
    ariaExpanded: {
        type: Boolean,
        default: false,
    },
    signingOut: {
        type: Boolean,
        default: false,
    },
    searchScopes: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Dashboard',
    },
    workspaceLabel: {
        type: String,
        default: 'GuidelyEdu workspace',
    },
    roleLabel: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['toggle-nav', 'sign-out']);

const router = useRouter();
const accountMenuOpen = ref(false);
const searchQuery = ref('');
const selectedScopeKey = ref(props.searchScopes[0]?.key ?? '');
/** @type {import('vue').Ref<HTMLElement|null>} */
const accountMenuRoot = ref(null);

const selectedScope = computed(() =>
    props.searchScopes.find((scope) => scope.key === selectedScopeKey.value)
        ?? props.searchScopes[0]
        ?? null,
);

watch(
    () => props.searchScopes,
    (scopes) => {
        if (!scopes.some((scope) => scope.key === selectedScopeKey.value)) {
            selectedScopeKey.value = scopes[0]?.key ?? '';
        }
    },
);

const initials = computed(() => {
    const parts = props.userName.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
});

function onSignOut() {
    accountMenuOpen.value = false;
    emit('sign-out');
}

function onSearch() {
    if (!selectedScope.value) {
        return;
    }

    const q = searchQuery.value.trim();

    router.push({
        path: selectedScope.value.to,
        query: q === '' ? {} : { q },
    });
}

function onDocumentPointerDown(event) {
    if (!accountMenuOpen.value) {
        return;
    }

    const target = event.target;

    if (!(target instanceof Node)) {
        return;
    }

    const root = accountMenuRoot.value;

    if (root && !root.contains(target)) {
        accountMenuOpen.value = false;
    }
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape' && accountMenuOpen.value) {
        accountMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
});
</script>
