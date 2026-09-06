<template>
    <button
        type="button"
        class="inline-flex items-center justify-center rounded-md bg-secondary px-4 py-2 text-body font-medium text-secondary-foreground hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
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
