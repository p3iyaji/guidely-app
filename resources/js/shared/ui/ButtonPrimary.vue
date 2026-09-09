<template>
    <button
        :type="type"
        class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-body font-semibold text-primary-foreground hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-focus-ring disabled:opacity-60"
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
    type: {
        type: String,
        default: 'button',
    },
});
</script>
