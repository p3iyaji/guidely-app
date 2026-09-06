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
            <label class="sr-only" for="shell-search">Search</label>
            <div class="relative hidden min-w-0 max-w-xs flex-1 sm:block md:max-w-sm" data-testid="search-stub">
                <input
                    id="shell-search"
                    type="search"
                    readonly
                    placeholder="Search Pupils or Review Cycles"
                    class="w-full rounded-sm border-0 bg-white/95 px-3 py-1.5 text-body text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-white"
                    title="Search is limited to Pupils and Review Cycles within your scope"
                >
            </div>

            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/20 text-label font-medium text-text-inverse"
                :title="userName"
                data-testid="avatar"
                aria-label="Account"
            >
                {{ initials }}
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue';
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
});

defineEmits(['toggle-nav']);

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
</script>
