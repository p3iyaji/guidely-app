<template>
    <div
        class="rounded-lg border border-border bg-surface"
        :class="padded ? 'p-5 sm:p-6' : ''"
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
