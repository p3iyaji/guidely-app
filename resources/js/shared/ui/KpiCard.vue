<template>
    <Card class="relative" data-testid="kpi-card">
        <button
            v-if="interactive"
            type="button"
            class="w-full rounded-md text-left focus:outline-none focus:ring-2 focus:ring-focus-ring"
            :class="selected ? 'ring-2 ring-focus-ring' : ''"
            :aria-pressed="selected"
            data-testid="kpi-card-select"
            @click="$emit('select')"
        >
            <p class="text-label font-medium text-text-muted">{{ label }}</p>
            <p class="mt-2 text-metric font-bold text-text" data-testid="kpi-value">
                {{ displayValue }}
            </p>
        </button>
        <template v-else>
            <p class="text-label font-medium text-text-muted">{{ label }}</p>
            <p class="mt-2 text-metric font-bold text-text" data-testid="kpi-value">
                {{ displayValue }}
            </p>
        </template>
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
    interactive: {
        type: Boolean,
        default: false,
    },
    selected: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['select']);

const displayValue = computed(() => (props.value === null || props.value === undefined ? '—' : props.value));
</script>
