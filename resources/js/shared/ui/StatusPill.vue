<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-label font-medium"
        :class="toneClass"
        data-testid="status-pill"
    >
        <span aria-hidden="true" data-testid="status-pill-icon">{{ icon }}</span>
        {{ label }}
        <span
            v-if="isEvaluating"
            class="inline-block size-3 animate-spin rounded-full border-2 border-current border-r-transparent"
            aria-hidden="true"
            data-testid="status-pill-spinner"
        />
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

const isEvaluating = computed(() => props.status === 'evaluating');
const label = computed(() => labels[props.status] ?? props.status);
const icon = computed(() => icons[props.status] ?? '●');
const toneClass = computed(() => tones[props.status] ?? tones['not-started']);
</script>
