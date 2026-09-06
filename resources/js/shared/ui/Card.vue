<template>
    <div
        class="rounded-lg border border-border bg-surface shadow-[0_1px_3px_rgba(31,41,55,0.08)]"
        :class="padded ? 'p-6' : ''"
    >
        <slot />
    </div>
</template>

<script setup>
defineProps({
    padded: {
        type: Boolean,
        default: true,
    },
});
</script>
