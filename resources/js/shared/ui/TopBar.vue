<template>
    <header
        class="flex h-topbar shrink-0 items-center gap-4 bg-topbar px-[var(--spacing-page)] text-text-inverse"
        data-testid="top-bar"
    >
        <div class="flex items-center gap-3">
            <button
                v-if="showMenuToggle"
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md text-text-inverse hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-white lg:hidden"
                aria-label="Toggle navigation"
                :aria-expanded="ariaExpanded"
                data-testid="nav-toggle"
                @click="$emit('toggle-nav')"
            >
                <span aria-hidden="true" class="text-heading leading-none">☰</span>
            </button>
            <BrandWordmark />
        </div>

        <div class="ml-auto flex min-w-0 flex-1 items-center justify-end gap-3 sm:gap-4">
            <form
                class="relative hidden min-w-0 max-w-xs flex-1 sm:block md:max-w-sm"
                data-testid="search-stub"
                @submit.prevent="onSearch"
            >
                <label class="sr-only" for="shell-search">Search</label>
                <input
                    id="shell-search"
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search Review Cycles"
                    class="w-full rounded-sm border-0 bg-white/95 px-3 py-1.5 text-body text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-white"
                    title="Search is limited to Review Cycles within your scope"
                >
            </form>

            <div ref="accountMenuRoot" class="relative" data-testid="account-menu">
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/20 text-label font-medium text-text-inverse hover:bg-white/30 focus:outline-none focus:ring-2 focus:ring-white"
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
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import BrandWordmark from './BrandWordmark.vue';

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
});

const emit = defineEmits(['toggle-nav', 'sign-out']);

const router = useRouter();
const accountMenuOpen = ref(false);
const searchQuery = ref('');
/** @type {import('vue').Ref<HTMLElement|null>} */
const accountMenuRoot = ref(null);

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
    const q = searchQuery.value.trim();

    router.push({
        path: '/review-cycles',
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
