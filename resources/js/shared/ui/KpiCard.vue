<template>
    <Card class="relative overflow-hidden" data-testid="kpi-card">
        <button
            v-if="interactive"
            type="button"
            class="w-full rounded-md text-left focus:outline-none focus:ring-2 focus:ring-focus-ring"
            :class="selected ? 'ring-2 ring-focus-ring' : ''"
            :aria-pressed="selected"
            data-testid="kpi-card-select"
            @click="$emit('select')"
        >
            <div class="flex items-start justify-between gap-3">
                <p class="text-label font-medium text-text-muted">{{ label }}</p>
                <span v-if="icon" class="grid size-8 place-items-center rounded-full bg-primary-soft text-primary">
                    <AppIcon :name="icon" class="size-4" />
                </span>
            </div>
            <p class="mt-3 font-display text-metric font-bold text-text" data-testid="kpi-value">
                {{ displayValue }}
            </p>
            <p v-if="detail" class="mt-1 text-meta text-text-muted">{{ detail }}</p>
        </button>
        <template v-else>
            <div class="flex items-start justify-between gap-3">
                <p class="text-label font-medium text-text-muted">{{ label }}</p>
                <span v-if="icon" class="grid size-8 place-items-center rounded-full bg-primary-soft text-primary">
                    <AppIcon :name="icon" class="size-4" />
                </span>
            </div>
            <p class="mt-3 font-display text-metric font-bold text-text" data-testid="kpi-value">
                {{ displayValue }}
            </p>
            <p v-if="detail" class="mt-1 text-meta text-text-muted">{{ detail }}</p>
        </template>
    </Card>
</template>

<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
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
    icon: {
        type: String,
        default: '',
    },
    detail: {
        type: String,
        default: '',
    },
});

defineEmits(['select']);

const displayValue = computed(() => (props.value === null || props.value === undefined ? '—' : props.value));
</script>
