<template>
    <button
        type="button"
        class="inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-surface px-4 py-2 text-body font-semibold text-text hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
        :disabled="disabled"
    >
        <slot />
    </button>
</template>

<script setup>
defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>
