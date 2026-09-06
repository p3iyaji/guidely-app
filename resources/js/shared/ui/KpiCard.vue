<template>
    <Card class="relative" data-testid="kpi-card">
        <p class="text-label font-medium text-text-muted">{{ label }}</p>
        <p class="mt-2 text-metric font-bold text-text" data-testid="kpi-value">
            {{ displayValue }}
        </p>
    </Card>
</template>

<script setup>
import { computed } from 'vue';
import Card from './Card.vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        default: null,
    },
});

const displayValue = computed(() => (props.value === null || props.value === undefined ? '—' : props.value));
</script>
