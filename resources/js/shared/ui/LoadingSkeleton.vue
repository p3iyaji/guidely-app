<template>
    <div
        class="animate-pulse rounded-lg bg-surface-muted"
        :class="sizeClass"
        role="status"
        aria-label="Loading"
        data-testid="loading-skeleton"
    />
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'card',
        validator: (value) => ['card', 'line', 'metric'].includes(value),
    },
});

const sizeClass = computed(() => {
    if (props.variant === 'line') {
        return 'h-4 w-full';
    }

    if (props.variant === 'metric') {
        return 'h-10 w-24';
    }

    return 'h-28 w-full';
});
</script>
