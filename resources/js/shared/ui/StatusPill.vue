<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-label font-medium"
        :class="toneClass"
        data-testid="status-pill"
    >
        <span aria-hidden="true">{{ icon }}</span>
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
        validator: (value) =>
            ['ready', 'gaps', 'uncovered', 'not-started', 'evaluating'].includes(value),
    },
});

const labels = {
    ready: 'Ready',
    gaps: 'Gaps',
    uncovered: 'Uncovered',
    'not-started': 'Not started',
    evaluating: 'Evaluating',
};

const icons = {
    ready: '●',
    gaps: '●',
    uncovered: '●',
    'not-started': '○',
    evaluating: '◐',
};

const tones = {
    ready: 'bg-success-soft text-success',
    gaps: 'bg-warning-soft text-warning',
    uncovered: 'bg-danger-soft text-danger',
    'not-started': 'bg-surface-muted text-text-muted',
    evaluating: 'bg-info-soft text-info',
};

const label = computed(() => labels[props.status] ?? props.status);
const icon = computed(() => icons[props.status] ?? '●');
const toneClass = computed(() => tones[props.status] ?? tones['not-started']);
</script>
